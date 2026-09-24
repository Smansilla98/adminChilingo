<?php

namespace App\Domain\Acceso;

use App\Models\Asignacion;
use App\Models\Beca;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calcula los roles contextuales y permisos efectivos de una persona a partir de:
 * asignaciones explícitas + relaciones académicas (derivadas) + roles heredados.
 */
final class ResolvedorAcceso
{
    /** @var array<string, bool> Existencia de tablas/columnas (esquemas legacy parciales). */
    private array $tablas = [];

    public function paraUsuario(User $user): PermisosEfectivos
    {
        if (isset($user->activo) && ! $user->activo) {
            return PermisosEfectivos::ninguno();
        }

        $roles = $this->rolesLegacy($user);
        $roles = array_merge($roles, $this->rolesDePersona($user->persona_id, (int) $user->id));
        $directos = $user->persona_id ? $this->permisosDirectos((int) $user->persona_id) : [];

        return $this->componer($user->persona_id ? (int) $user->persona_id : null, $roles, $directos);
    }

    /** Para mostrar funciones de personas sin cuenta (ficha de persona). */
    public function paraPersona(Persona $persona): PermisosEfectivos
    {
        $userId = $persona->user?->id;
        $roles = $this->rolesDePersona((int) $persona->id, $userId ? (int) $userId : null);
        if ($persona->user) {
            $roles = array_merge($this->rolesLegacy($persona->user), $roles);
        }

        return $this->componer((int) $persona->id, $roles, $this->permisosDirectos((int) $persona->id));
    }

    /**
     * @param  list<RolContextual>  $roles
     * @param  list<array{permiso: string, rol: RolContextual}>  $directos
     */
    private function componer(?int $personaId, array $roles, array $directos): PermisosEfectivos
    {
        // Deduplicar roles por clave (mismo rol, ámbito, sede, bloque), priorizando asignaciones.
        $unicos = [];
        foreach ($roles as $r) {
            $k = $r->clave();
            if (! isset($unicos[$k]) || $r->origen === RolContextual::ORIGEN_ASIGNACION) {
                $unicos[$k] = $r;
            }
        }
        $roles = array_values($unicos);

        $superadmin = false;
        $permisos = [];
        $origenes = [];

        $aplicar = function (string $permiso, RolContextual $r, string $origen) use (&$permisos, &$origenes) {
            $alcance = $permisos[$permiso] ??= Alcance::vacio();
            match ($r->ambito) {
                'global' => $alcance->agregarGlobal(),
                'sede' => $alcance->agregarSede((int) $r->sedeId),
                'bloque' => $alcance->agregarBloque((int) $r->bloqueId, $r->sedeId),
                default => null,
            };
            $origenes[$permiso][] = $origen;
        };

        foreach ($roles as $r) {
            if ($r->rol === 'superadministrador' && $r->ambito === 'global') {
                $superadmin = true;
            }
            foreach (CatalogoPermisos::permisosDeRol($r->rol) as $p) {
                $aplicar($p, $r, 'rol:'.$r->clave());
            }
        }
        foreach ($directos as $d) {
            $aplicar($d['permiso'], $d['rol'], 'permiso:'.$d['rol']->clave());
        }

        foreach ($origenes as $p => $lista) {
            $origenes[$p] = array_values(array_unique($lista));
        }

        return new PermisosEfectivos($personaId, $roles, $permisos, $origenes, $superadmin);
    }

    /**
     * users.role y roles Spatie globales de dirección.
     *
     * @return list<RolContextual>
     */
    private function rolesLegacy(User $user): array
    {
        $nombres = [];
        if (in_array($user->role, ['admin', 'direccion'], true)) {
            $nombres[] = $user->role;
        }
        if ($this->hayTabla('model_has_roles')) {
            $nombres = array_merge($nombres, DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', $user->getMorphClass())
                ->where('model_has_roles.model_id', $user->id)
                ->pluck('roles.name')
                ->all());
        }

        $out = [];
        foreach (array_unique($nombres) as $nombre) {
            $rol = CatalogoPermisos::rolLegacy($nombre)
                ?? (in_array($nombre, ['administrador', 'superadministrador'], true) ? $nombre : null);
            if ($rol) {
                $out[] = new RolContextual($rol, 'global', origen: RolContextual::ORIGEN_LEGACY);
            }
        }

        return $out;
    }

    /**
     * Roles derivados de las relaciones académicas de la persona (o del usuario, para
     * perfiles legacy vinculados solo por user_id).
     *
     * @return list<RolContextual>
     */
    private function rolesDePersona(?int $personaId, ?int $userId): array
    {
        if (! $personaId && ! $userId) {
            return [];
        }

        $roles = [];
        $vinculo = fn (string $tabla) => function ($q) use ($tabla, $personaId, $userId) {
            $porPersona = $personaId && $this->hayColumna($tabla, 'persona_id');
            if (! $porPersona && ! $userId) {
                // Un grupo vacío no filtraría nada: sin vínculo, ningún perfil.
                $q->whereRaw('1 = 0');

                return;
            }
            $q->where(function ($w) use ($porPersona, $personaId, $userId) {
                if ($porPersona) {
                    $w->orWhere('persona_id', $personaId);
                }
                if ($userId) {
                    $w->orWhere('user_id', $userId);
                }
            });
        };

        // Docente
        $profesorIds = DB::table('profesores')->where('activo', true)->where($vinculo('profesores'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($profesorIds !== []) {
            $bloques = DB::table('bloques')->whereIn('profesor_id', $profesorIds)->pluck('sede_id', 'id')->all();
            if ($this->hayTabla('bloque_profesor')) {
                $bloques += DB::table('bloque_profesor')
                    ->join('bloques', 'bloques.id', '=', 'bloque_profesor.bloque_id')
                    ->whereIn('bloque_profesor.profesor_id', $profesorIds)
                    ->pluck('bloques.sede_id', 'bloques.id')
                    ->all();
            }
            foreach ($bloques as $bloqueId => $sedeId) {
                $roles[] = new RolContextual('profesor', 'bloque', $sedeId ? (int) $sedeId : null, (int) $bloqueId, RolContextual::ORIGEN_DOCENTE);
            }

            $sedesDocente = array_values(array_filter(array_map('intval', $bloques)));
            if ($this->hayTabla('profesor_sede')) {
                foreach (DB::table('profesor_sede')->whereIn('profesor_id', $profesorIds)->get(['sede_id', 'rol']) as $ps) {
                    $sedesDocente[] = (int) $ps->sede_id;
                    if (in_array($ps->rol, ['coordinador', 'encargado'], true)) {
                        $roles[] = new RolContextual($ps->rol, 'sede', (int) $ps->sede_id, null, RolContextual::ORIGEN_SEDE);
                    }
                }
            }
            if ($this->hayColumna('sedes', 'coordinador_id')) {
                foreach (DB::table('sedes')->whereIn('coordinador_id', $profesorIds)->pluck('id') as $sid) {
                    $roles[] = new RolContextual('coordinador', 'sede', (int) $sid, null, RolContextual::ORIGEN_SEDE);
                    $sedesDocente[] = (int) $sid;
                }
            }
            if ($this->hayTabla('coordinador_area') && DB::table('coordinador_area')->whereIn('profesor_id', $profesorIds)->exists()) {
                foreach (array_unique($sedesDocente) as $sid) {
                    $roles[] = new RolContextual('coordinador_area', 'sede', $sid, null, RolContextual::ORIGEN_AREA);
                }
            }
        }

        // Alumno
        $alumnos = DB::table('alumnos')->where('activo', true)->where($vinculo('alumnos'))->get(['id', 'sede_id', 'bloque_id']);
        if ($alumnos->isNotEmpty()) {
            $alumnoIds = $alumnos->pluck('id')->all();
            $bloques = [];
            if ($this->hayTabla('alumno_bloque')) {
                $bloques = DB::table('alumno_bloque')
                    ->join('bloques', 'bloques.id', '=', 'alumno_bloque.bloque_id')
                    ->whereIn('alumno_bloque.alumno_id', $alumnoIds)
                    ->pluck('bloques.sede_id', 'bloques.id')
                    ->all();
            }
            $legacy = $alumnos->pluck('bloque_id')->filter()->diff(array_keys($bloques))->all();
            if ($legacy !== []) {
                $bloques += DB::table('bloques')->whereIn('id', $legacy)->pluck('sede_id', 'id')->all();
            }
            foreach ($bloques as $bloqueId => $sedeId) {
                $roles[] = new RolContextual('alumno', 'bloque', $sedeId ? (int) $sedeId : null, (int) $bloqueId, RolContextual::ORIGEN_ALUMNO);
            }
            if ($bloques === []) {
                foreach ($alumnos->pluck('sede_id')->filter()->unique() as $sid) {
                    $roles[] = new RolContextual('alumno', 'sede', (int) $sid, null, RolContextual::ORIGEN_ALUMNO);
                }
            }

            if ($this->hayTabla('becas') && Beca::query()->whereIn('alumno_id', $alumnoIds)->vigentesEn(now())->exists()) {
                $roles[] = new RolContextual('becado', 'global', origen: RolContextual::ORIGEN_BECA);
            }
        }

        // Asignaciones explícitas de rol
        if ($personaId && $this->hayTabla('asignaciones')) {
            $asignaciones = Asignacion::query()
                ->vigentes()
                ->where('persona_id', $personaId)
                ->whereNotNull('role_id')
                ->with(['role:id,name', 'bloque:id,sede_id'])
                ->get();
            foreach ($asignaciones as $a) {
                if (! $a->role) {
                    continue;
                }
                $roles[] = new RolContextual(
                    $a->role->name,
                    $a->ambito_tipo,
                    $a->ambito_tipo === 'bloque' ? ($a->bloque?->sede_id ? (int) $a->bloque->sede_id : null) : ($a->sede_id ? (int) $a->sede_id : null),
                    $a->bloque_id ? (int) $a->bloque_id : null,
                    RolContextual::ORIGEN_ASIGNACION,
                    (int) $a->id,
                );
            }
        }

        return $roles;
    }

    /**
     * Permisos sueltos asignados a la persona.
     *
     * @return list<array{permiso: string, rol: RolContextual}>
     */
    private function permisosDirectos(int $personaId): array
    {
        if (! $this->hayTabla('asignaciones')) {
            return [];
        }

        $out = [];
        $filas = Asignacion::query()
            ->vigentes()
            ->where('persona_id', $personaId)
            ->whereNotNull('permission_id')
            ->with(['permission:id,name', 'bloque:id,sede_id'])
            ->get();
        foreach ($filas as $a) {
            if (! $a->permission) {
                continue;
            }
            $out[] = [
                'permiso' => $a->permission->name,
                'rol' => new RolContextual(
                    'permiso:'.$a->permission->name,
                    $a->ambito_tipo,
                    $a->ambito_tipo === 'bloque' ? ($a->bloque?->sede_id ? (int) $a->bloque->sede_id : null) : ($a->sede_id ? (int) $a->sede_id : null),
                    $a->bloque_id ? (int) $a->bloque_id : null,
                    RolContextual::ORIGEN_ASIGNACION,
                    (int) $a->id,
                ),
            ];
        }

        return $out;
    }

    private function hayTabla(string $tabla): bool
    {
        return $this->tablas[$tabla] ??= Schema::hasTable($tabla);
    }

    private function hayColumna(string $tabla, string $columna): bool
    {
        return $this->tablas[$tabla.'.'.$columna] ??= Schema::hasColumn($tabla, $columna);
    }
}
