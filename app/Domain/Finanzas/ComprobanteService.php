<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\User;
use App\Services\ComprobanteCuotaRegistroService;
use App\Services\PagoDesdeComprobanteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comprobantes de cuota enviados por alumnos o cargados por la escuela (web y API).
 * Se revisan (quedan "vistos") o se aprueban: aprobar registra el pago.
 */
class ComprobanteService
{
    public const ESTADOS = ['pendiente' => 'Pendiente de revisión', 'visto' => 'Visto', 'pagado' => 'Pagado'];

    public function __construct(
        private ComprobanteCuotaRegistroService $registro,
        private PagoDesdeComprobanteService $pagos,
    ) {}

    /** Comprobantes del alcance de `comprobantes.view` (sede del comprobante o bloques de sus ítems). */
    public function consulta(User $user): Builder
    {
        $query = ComprobanteCuotaAlumno::query();
        $alcance = $user->acceso()->alcance('comprobantes.view');
        if (! $alcance->esGlobal()) {
            $query->where(function ($q) use ($alcance) {
                $q->whereIn('sede_id', $alcance->sedeIds() ?: [0])
                    ->orWhereHas('items', fn ($i) => $i->whereIn('bloque_id', $alcance->bloqueIds() ?: [0]))
                    ->orWhereHas('items.bloque', fn ($b) => $b->whereIn('sede_id', $alcance->sedeIds() ?: [0]));
            });
        }

        return $query;
    }

    public function puedeVer(User $user, ComprobanteCuotaAlumno $c): bool
    {
        $c->loadMissing(['items.bloque']);
        $alcance = $user->acceso()->alcance('comprobantes.view');

        return $alcance->incluyeSede((int) $c->sede_id)
            || $c->items->contains(fn ($i) => $alcance->incluyeBloque((int) $i->bloque_id, $i->bloque?->sede_id ? (int) $i->bloque->sede_id : null));
    }

    public function puedeAprobar(User $user, ComprobanteCuotaAlumno $c): bool
    {
        $c->loadMissing('alumno');

        return $c->alumno !== null && $user->acceso()->puedeSobreAlumno('comprobantes.approve', $c->alumno);
    }

    /** Sede de referencia del alumno (principal, la de su bloque o la del primer bloque). */
    public function sedeDelAlumno(Alumno $alumno): int
    {
        $alumno->loadMissing(['bloques', 'bloque']);

        return (int) ($alumno->sede_id ?: $alumno->bloque?->sede_id ?: $alumno->bloques->first()?->sede_id);
    }

    /**
     * Carga desde la escuela (docente, secretaría) con los permisos de `comprobantes.create`.
     *
     * @param  list<int>  $bloqueIds
     */
    public function cargarPorGestion(User $por, Alumno $alumno, int $anio, int $mes, string $fechaPago, array $bloqueIds, UploadedFile $archivo, ?string $notas): ComprobanteCuotaAlumno
    {
        $acceso = $por->acceso();
        if (! $acceso->puedeSobreAlumno('comprobantes.create', $alumno)) {
            abort(403, 'No podés cargar comprobantes de este alumno.');
        }
        foreach (array_map('intval', $bloqueIds) as $bid) {
            if (! $acceso->puedeEnBloque('comprobantes.create', $bid)) {
                abort(403, 'No podés cargar comprobantes de ese bloque.');
            }
        }

        return $this->registrar($alumno, $anio, $mes, $fechaPago, $bloqueIds, $archivo, $notas ?: 'Cargado por docente/administración.', $por);
    }

    /**
     * El propio alumno (o quien es la persona del alumno) envía su comprobante.
     *
     * @param  list<int>  $bloqueIds
     */
    public function enviarPropio(User $por, Alumno $alumno, int $anio, int $mes, string $fechaPago, array $bloqueIds, UploadedFile $archivo, ?string $notas): ComprobanteCuotaAlumno
    {
        $propio = ($por->persona_id && (int) $alumno->persona_id === (int) $por->persona_id) || ((int) $alumno->user_id === (int) $por->id);
        if (! $propio) {
            abort(403, 'Solo podés enviar comprobantes de tus propias cuotas.');
        }

        return $this->registrar($alumno, $anio, $mes, $fechaPago, $bloqueIds, $archivo, $notas, $por);
    }

    public function marcarVisto(ComprobanteCuotaAlumno $c): ComprobanteCuotaAlumno
    {
        if (! $c->estaPagado()) {
            $c->update(['estado' => 'visto']);
        }

        return $c;
    }

    /**
     * Aprueba y registra el pago. La fila se bloquea: dos aprobaciones simultáneas no
     * generan dos pagos.
     *
     * @return array{pago: \App\Models\Pago, mensaje: string}
     */
    public function aprobar(ComprobanteCuotaAlumno $c, User $por, bool $liquidarProfesor = true): array
    {
        return DB::transaction(function () use ($c, $por, $liquidarProfesor) {
            $bloqueado = ComprobanteCuotaAlumno::query()->lockForUpdate()->findOrFail($c->id);

            return $this->pagos->aprobar($bloqueado, (int) $por->id, $liquidarProfesor);
        });
    }

    /**
     * Bloques del alumno con la cuota que corresponde al período (para elegir qué se paga).
     *
     * @return list<array{id: int, nombre: string, cuota_id: ?int, cuota: ?string, monto: ?float, ya_pagada: bool}>
     */
    public function opciones(Alumno $alumno, int $anio, int $mes): array
    {
        $alumno->loadMissing(['bloques', 'bloque']);
        $bloques = $alumno->bloques->keyBy('id');
        if ($alumno->bloque && ! $bloques->has($alumno->bloque->id)) {
            $bloques->put($alumno->bloque->id, $alumno->bloque);
        }

        return $bloques->values()->filter(fn (Bloque $b) => $b->activo)->map(function (Bloque $b) use ($alumno, $anio, $mes) {
            $cuota = Cuota::resolveForBloque((int) $b->id, $anio, $mes);
            $aplica = $cuota && $cuota->aplicaAAlumno($alumno);

            return [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'cuota_id' => $aplica ? $cuota->id : null,
                'cuota' => $aplica ? $cuota->nombre : null,
                'monto' => $aplica ? (float) $cuota->monto : null,
                'ya_pagada' => $aplica && $this->registro->alumnoYaPagoCuota((int) $alumno->id, (int) $cuota->id),
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $bloqueIds
     */
    private function registrar(Alumno $alumno, int $anio, int $mes, string $fechaPago, array $bloqueIds, UploadedFile $archivo, ?string $notas, User $por): ComprobanteCuotaAlumno
    {
        $sedeId = $this->sedeDelAlumno($alumno);
        if ($sedeId <= 0) {
            throw ValidationException::withMessages(['alumno_id' => 'El alumno no tiene sede.']);
        }

        return $this->registro->registrar($alumno, $sedeId, $anio, $mes, $fechaPago, $bloqueIds, $archivo, $notas, (int) $por->id);
    }
}
