<?php

namespace App\Domain\Datos;

use App\Models\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Chequeos de integridad de datos (solo lectura). Cada chequeo devuelve una lista de
 * hallazgos con lo necesario para corregirlos a mano.
 */
class Diagnostico
{
    /**
     * @return array<string, array{titulo: string, severidad: string, items: list<array<string, mixed>>}>
     */
    public function ejecutar(): array
    {
        $chequeos = [
            'personas_duplicadas_dni' => ['Personas con el mismo DNI', 'alta', fn () => $this->personasMismoDni()],
            'personas_duplicadas_nombre' => ['Personas con el mismo nombre (posibles duplicados)', 'media', fn () => $this->personasMismoNombre()],
            'profesores_duplicados' => ['Profesores duplicados (mismo nombre o email)', 'media', fn () => $this->profesoresDuplicados()],
            'alumnos_duplicados' => ['Alumnos duplicados (mismo DNI o nombre + nacimiento)', 'alta', fn () => $this->alumnosDuplicados()],
            'perfiles_sin_persona' => ['Alumnos, profesores o usuarios sin persona', 'alta', fn () => $this->perfilesSinPersona()],
            'usuarios_huerfanos' => ['Usuarios sin ninguna función', 'baja', fn () => $this->usuariosSinFuncion()],
            'cuenta_y_perfil_divergen' => ['Perfil vinculado a una cuenta de otra persona', 'alta', fn () => $this->cuentaYPerfilDivergen()],
            'sedes_inexistentes' => ['Referencias a sedes inexistentes', 'alta', fn () => $this->referenciasRotas('sede_id', 'sedes', ['alumnos', 'bloques', 'inventario_items', 'gastos', 'eventos', 'cuotas'])],
            'bloques_inexistentes' => ['Referencias a bloques inexistentes', 'alta', fn () => $this->referenciasRotas('bloque_id', 'bloques', ['alumnos', 'alumno_bloque', 'asistencias', 'cuotas', 'eventos'])],
            'cuotas_inconsistentes' => ['Cuotas con alcance incompleto, monto inválido o duplicadas', 'media', fn () => $this->cuotasInconsistentes()],
            'pagos_inconsistentes' => ['Pagos cuyo total no coincide con el detalle, sin detalle o duplicados', 'alta', fn () => $this->pagosInconsistentes()],
            'sin_superadmin' => ['No hay ningún superadministrador', 'media', fn () => $this->sinSuperadmin()],
        ];

        $out = [];
        foreach ($chequeos as $clave => [$titulo, $severidad, $fn]) {
            try {
                $items = $fn();
            } catch (\Throwable $e) {
                $items = [['error' => 'No se pudo ejecutar: '.$e->getMessage()]];
            }
            $out[$clave] = ['titulo' => $titulo, 'severidad' => $severidad, 'items' => $items];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function personasMismoDni(): array
    {
        if (! Schema::hasTable('personas')) {
            return [];
        }

        return DB::table('personas')
            ->whereNull('deleted_at')->whereNull('fusionada_en_id')->whereNotNull('dni')
            ->select('dni', DB::raw('COUNT(*) as cantidad'), DB::raw('MIN(id) as primera'), DB::raw('MAX(id) as ultima'))
            ->groupBy('dni')->havingRaw('COUNT(*) > 1')
            ->get()->map(fn ($r) => ['dni' => $r->dni, 'cantidad' => (int) $r->cantidad, 'ids' => $this->idsPersonas('dni', $r->dni),
                'sugerencia' => "php artisan chilinga:personas:fusionar {$r->primera} {$r->ultima}"])->all();
    }

    /** @return list<array<string, mixed>> */
    private function personasMismoNombre(): array
    {
        if (! Schema::hasTable('personas')) {
            return [];
        }
        $grupos = [];
        DB::table('personas')->whereNull('deleted_at')->whereNull('fusionada_en_id')
            ->orderBy('id')->select('id', 'nombre', 'apellido', 'dni')
            ->chunkById(1000, function ($filas) use (&$grupos) {
                foreach ($filas as $p) {
                    $clave = Str::of(trim($p->nombre.' '.($p->apellido ?? '')))->ascii()->lower()->squish()->value();
                    if ($clave !== '') {
                        $grupos[$clave][] = ['id' => $p->id, 'dni' => $p->dni];
                    }
                }
            });

        $out = [];
        foreach ($grupos as $nombre => $personas) {
            if (count($personas) < 2) {
                continue;
            }
            $dnis = array_unique(array_filter(array_column($personas, 'dni')));
            if (count($dnis) > 1) {
                continue; // mismo nombre, DNI distintos: son personas distintas
            }
            $out[] = ['nombre' => $nombre, 'ids' => array_column($personas, 'id')];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function profesoresDuplicados(): array
    {
        $out = [];
        $porNombre = DB::table('profesores')->select('nombre', DB::raw('COUNT(*) as n'), DB::raw('GROUP_CONCAT(id) as ids'))
            ->groupBy('nombre')->havingRaw('COUNT(*) > 1')->get();
        foreach ($porNombre as $r) {
            $out[] = ['criterio' => 'nombre', 'valor' => $r->nombre, 'ids' => $r->ids];
        }
        if (Schema::hasColumn('profesores', 'email')) {
            $porEmail = DB::table('profesores')->whereNotNull('email')->where('email', '!=', '')
                ->select('email', DB::raw('GROUP_CONCAT(id) as ids'))->groupBy('email')->havingRaw('COUNT(*) > 1')->get();
            foreach ($porEmail as $r) {
                $out[] = ['criterio' => 'email', 'valor' => $r->email, 'ids' => $r->ids];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function alumnosDuplicados(): array
    {
        $out = [];
        $dnis = [];
        foreach (DB::table('alumnos')->whereNotNull('dni')->where('dni', '!=', '')->get(['id', 'dni']) as $a) {
            $dnis[Persona::normalizarDni($a->dni)][] = $a->id;
        }
        foreach ($dnis as $dni => $ids) {
            if (count($ids) > 1) {
                $out[] = ['criterio' => 'dni', 'valor' => $dni, 'ids' => implode(',', $ids)];
            }
        }
        $porNombre = DB::table('alumnos')->whereNotNull('fecha_nacimiento')
            ->select('nombre_apellido', 'fecha_nacimiento', DB::raw('GROUP_CONCAT(id) as ids'))
            ->groupBy('nombre_apellido', 'fecha_nacimiento')->havingRaw('COUNT(*) > 1')->get();
        foreach ($porNombre as $r) {
            $out[] = ['criterio' => 'nombre+nacimiento', 'valor' => $r->nombre_apellido.' '.$r->fecha_nacimiento, 'ids' => $r->ids];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function perfilesSinPersona(): array
    {
        if (! Schema::hasColumn('alumnos', 'persona_id')) {
            return [['tabla' => 'todas', 'detalle' => 'Faltan migraciones de personas. Corré php artisan migrate.']];
        }
        $out = [];
        foreach (['alumnos', 'profesores', 'users'] as $tabla) {
            $n = DB::table($tabla)->whereNull('persona_id')->count();
            if ($n > 0) {
                $out[] = ['tabla' => $tabla, 'cantidad' => $n, 'sugerencia' => 'php artisan chilinga:personas:backfill'];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function usuariosSinFuncion(): array
    {
        if (! Schema::hasTable('asignaciones')) {
            return [];
        }

        return DB::table('users')
            ->whereNotIn('role', ['admin', 'direccion'])
            ->whereNotExists(fn ($q) => $q->from('profesores')->whereColumn('profesores.user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->from('alumnos')->whereColumn('alumnos.user_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->from('asignaciones')->whereColumn('asignaciones.persona_id', 'users.persona_id'))
            ->whereNotExists(fn ($q) => $q->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereColumn('model_has_roles.model_id', 'users.id')->whereIn('roles.name', ['admin', 'direccion']))
            ->get(['id', 'username', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'username' => $u->username, 'email' => $u->email])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function cuentaYPerfilDivergen(): array
    {
        if (! Schema::hasColumn('alumnos', 'persona_id')) {
            return [];
        }
        $out = [];
        foreach (['alumnos', 'profesores'] as $tabla) {
            $filas = DB::table($tabla)->join('users', 'users.id', '=', $tabla.'.user_id')
                ->whereNotNull($tabla.'.persona_id')->whereNotNull('users.persona_id')
                ->whereColumn($tabla.'.persona_id', '!=', 'users.persona_id')
                ->get([$tabla.'.id', $tabla.'.persona_id', 'users.id as user_id', 'users.persona_id as persona_cuenta']);
            foreach ($filas as $f) {
                $out[] = ['tabla' => $tabla, 'id' => $f->id, 'persona_perfil' => $f->persona_id, 'user_id' => $f->user_id, 'persona_cuenta' => $f->persona_cuenta,
                    'sugerencia' => "php artisan chilinga:personas:fusionar {$f->persona_cuenta} {$f->persona_id}"];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $tablas
     * @return list<array<string, mixed>>
     */
    private function referenciasRotas(string $columna, string $destino, array $tablas): array
    {
        $out = [];
        foreach ($tablas as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
                continue;
            }
            $ids = DB::table($tabla)->whereNotNull($tabla.'.'.$columna)
                ->leftJoin($destino, $destino.'.id', '=', $tabla.'.'.$columna)
                ->whereNull($destino.'.id')
                ->pluck($tabla.'.'.$columna)->unique()->values()->all();
            if ($ids !== []) {
                $out[] = ['tabla' => $tabla, 'ids_inexistentes' => implode(',', $ids)];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function cuotasInconsistentes(): array
    {
        $out = [];
        foreach (DB::table('cuotas')->get() as $c) {
            $alcance = $c->alcance ?? 'bloque';
            $problemas = [];
            if ($alcance === 'bloque' && ! $c->bloque_id) {
                $problemas[] = 'alcance bloque sin bloque';
            }
            if ($alcance === 'sede' && ! $c->sede_id) {
                $problemas[] = 'alcance sede sin sede';
            }
            if ((float) $c->monto <= 0) {
                $problemas[] = 'monto <= 0';
            }
            if ($c->mes !== null && ($c->mes < 1 || $c->mes > 12)) {
                $problemas[] = 'mes inválido';
            }
            if ($problemas !== []) {
                $out[] = ['cuota_id' => $c->id, 'nombre' => $c->nombre, 'problemas' => implode('; ', $problemas)];
            }
        }
        $dup = DB::table('cuotas')->whereNotNull('mes')
            ->select('año', 'mes', 'alcance', 'bloque_id', 'sede_id', DB::raw('GROUP_CONCAT(id) as ids'))
            ->groupBy('año', 'mes', 'alcance', 'bloque_id', 'sede_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($dup as $d) {
            $out[] = ['cuota_id' => $d->ids, 'nombre' => "Duplicada {$d->mes}/{$d->año} ({$d->alcance})", 'problemas' => 'misma cuota dos veces en el período'];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function pagosInconsistentes(): array
    {
        $out = [];
        $anulados = Schema::hasColumn('pagos', 'anulado_at');
        $pagos = DB::table('pagos')
            ->leftJoin('pago_detalles', 'pago_detalles.pago_id', '=', 'pagos.id')
            ->when($anulados, fn ($q) => $q->whereNull('pagos.anulado_at'))
            ->groupBy('pagos.id', 'pagos.monto_total')
            ->select('pagos.id', 'pagos.monto_total', DB::raw('COALESCE(SUM(pago_detalles.monto), 0) as suma'), DB::raw('COUNT(pago_detalles.id) as lineas'))
            ->get();
        foreach ($pagos as $p) {
            if ((int) $p->lineas === 0) {
                $out[] = ['pago_id' => $p->id, 'problema' => 'sin detalle'];
            } elseif (abs((float) $p->monto_total - (float) $p->suma) > 0.02) {
                $out[] = ['pago_id' => $p->id, 'problema' => 'total '.$p->monto_total.' ≠ detalle '.$p->suma];
            }
        }
        $dobles = DB::table('pago_detalles')->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
            ->when($anulados, fn ($q) => $q->whereNull('pagos.anulado_at'))
            ->select('pago_detalles.alumno_id', 'pago_detalles.cuota_id', DB::raw('COUNT(DISTINCT pago_detalles.pago_id) as n'))
            ->groupBy('pago_detalles.alumno_id', 'pago_detalles.cuota_id')->havingRaw('COUNT(DISTINCT pago_detalles.pago_id) > 1')->get();
        foreach ($dobles as $d) {
            $out[] = ['pago_id' => '-', 'problema' => "alumno {$d->alumno_id} pagó la cuota {$d->cuota_id} en {$d->n} pagos"];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function sinSuperadmin(): array
    {
        if (! Schema::hasTable('asignaciones')) {
            return [];
        }
        $hay = DB::table('asignaciones')->join('roles', 'roles.id', '=', 'asignaciones.role_id')
            ->where('roles.name', 'superadministrador')->where('asignaciones.activo', true)->exists();

        return $hay ? [] : [['detalle' => 'Nadie puede asignar roles de administración.', 'sugerencia' => 'php artisan chilinga:superadmin {usuario}']];
    }

    /** @return list<int> */
    private function idsPersonas(string $columna, string $valor): array
    {
        return DB::table('personas')->whereNull('deleted_at')->where($columna, $valor)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
