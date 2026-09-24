<?php

namespace App\Domain\Personas;

use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Crea personas para los perfiles existentes. Idempotente: solo toca filas sin persona_id.
 *
 * Reglas (conservadoras, nunca adivina):
 *  1. Perfiles que comparten user_id → misma persona (ya estaban unidos por la cuenta).
 *  2. Alumnos con el mismo DNI (normalizado) → misma persona.
 *  3. El resto → una persona por perfil. Posibles duplicados por nombre los reporta
 *     `chilinga:diagnose` y se fusionan a mano (`chilinga:personas:fusionar`).
 */
class BackfillPersonas
{
    /**
     * @return array{personas_creadas: int, alumnos: int, profesores: int, usuarios: int, admins: int}
     */
    public function ejecutar(bool $simular = false): array
    {
        $stats = ['personas_creadas' => 0, 'alumnos' => 0, 'profesores' => 0, 'usuarios' => 0, 'admins' => 0];
        if (! Schema::hasTable('personas') || ! Schema::hasColumn('alumnos', 'persona_id')) {
            return $stats;
        }

        $run = function () use (&$stats, $simular) {
            $crear = function (array $datos) use (&$stats, $simular): int {
                $stats['personas_creadas']++;
                if ($simular) {
                    return -$stats['personas_creadas'];
                }

                return (int) Persona::query()->create($datos + ['estado' => 'activo'])->id;
            };
            $asignar = function (string $tabla, int $id, int $personaId) use (&$stats, $simular) {
                $stats[$tabla === 'users' ? 'usuarios' : $tabla]++;
                if (! $simular) {
                    DB::table($tabla)->where('id', $id)->whereNull('persona_id')->update(['persona_id' => $personaId]);
                }
            };

            // 1) Por cuenta de usuario: usuario + profesor + alumnos comparten persona.
            $users = DB::table('users')->whereNull('persona_id')->orderBy('id')->get();
            foreach ($users as $u) {
                $prof = DB::table('profesores')->where('user_id', $u->id)->orderBy('id')->first();
                $alums = DB::table('alumnos')->where('user_id', $u->id)->orderBy('id')->get();
                $existente = $prof?->persona_id ?? $alums->firstWhere('persona_id', '!=', null)?->persona_id;
                $alum = $alums->first();

                $personaId = $existente ? (int) $existente : $crear([
                    'nombre' => (string) ($alum->nombre_apellido ?? $prof->nombre ?? ($u->name ?: $u->username)),
                    'dni' => Persona::normalizarDni($alum->dni ?? null),
                    'fecha_nacimiento' => $alum->fecha_nacimiento ?? null,
                    'telefono' => $alum->telefono ?? $prof->telefono ?? ($u->telefono ?? null),
                    'email' => $prof->email ?? $u->email,
                ]);
                $asignar('users', (int) $u->id, $personaId);
                if ($prof && ! $prof->persona_id) {
                    $asignar('profesores', (int) $prof->id, $personaId);
                }
                foreach ($alums as $a) {
                    if (! $a->persona_id) {
                        $asignar('alumnos', (int) $a->id, $personaId);
                    }
                }
            }

            // 2) Alumnos restantes; mismo DNI → misma persona.
            $porDni = [];
            DB::table('alumnos')->whereNull('persona_id')->orderBy('id')->chunkById(500, function ($alumnos) use (&$porDni, $crear, $asignar) {
                foreach ($alumnos as $a) {
                    $dni = Persona::normalizarDni($a->dni);
                    if ($dni && isset($porDni[$dni])) {
                        $personaId = $porDni[$dni];
                    } else {
                        $existente = $dni ? DB::table('personas')->where('dni', $dni)->whereNull('fusionada_en_id')->value('id') : null;
                        $personaId = $existente ? (int) $existente : $crear([
                            'nombre' => (string) $a->nombre_apellido,
                            'dni' => $dni,
                            'fecha_nacimiento' => $a->fecha_nacimiento,
                            'telefono' => $a->telefono,
                        ]);
                        if ($dni) {
                            $porDni[$dni] = $personaId;
                        }
                    }
                    $asignar('alumnos', (int) $a->id, $personaId);
                }
            });

            // 3) Profesores sin cuenta.
            DB::table('profesores')->whereNull('persona_id')->orderBy('id')->chunkById(500, function ($profes) use ($crear, $asignar) {
                foreach ($profes as $p) {
                    $asignar('profesores', (int) $p->id, $crear([
                        'nombre' => (string) $p->nombre,
                        'telefono' => $p->telefono ?? null,
                        'email' => $p->email ?? null,
                    ]));
                }
            });

            // 4) Dirección heredada → asignación explícita "superadministrador" global.
            //    Paridad: antes todo admin podía crear otros admins; los administradores
            //    que se creen desde ahora ya no pueden (ver docs/ROLES_Y_PERMISOS.md).
            if (! $simular && Schema::hasTable('asignaciones')) {
                $rolAdmin = Role::query()->where('name', 'superadministrador')->where('guard_name', 'web')->first();
                if ($rolAdmin) {
                    $legacyIds = DB::table('users')->whereIn('role', ['admin', 'direccion'])->pluck('id')->all();
                    if (Schema::hasTable('model_has_roles')) {
                        $legacyIds = array_merge($legacyIds, DB::table('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereIn('roles.name', ['admin', 'direccion'])
                            ->where('model_has_roles.model_type', \App\Models\User::class)
                            ->pluck('model_has_roles.model_id')
                            ->all());
                    }
                    foreach (DB::table('users')->whereIn('id', array_unique($legacyIds))->whereNotNull('persona_id')->get(['id', 'persona_id']) as $u) {
                        $existe = Asignacion::query()->where('persona_id', $u->persona_id)->where('role_id', $rolAdmin->id)
                            ->where('ambito_tipo', 'global')->exists();
                        if (! $existe) {
                            Asignacion::query()->create([
                                'persona_id' => $u->persona_id,
                                'role_id' => $rolAdmin->id,
                                'ambito_tipo' => 'global',
                                'activo' => true,
                                'notas' => 'Migrado desde rol heredado (admin/dirección) con los mismos privilegios.',
                            ]);
                            $stats['admins']++;
                        }
                    }
                }
            }
        };

        if ($simular) {
            $run(); // en simulación no se escribe nada: solo se cuentan los cambios
        } else {
            Auditoria::sinRegistrar(fn () => DB::transaction($run));
        }

        return $stats;
    }
}
