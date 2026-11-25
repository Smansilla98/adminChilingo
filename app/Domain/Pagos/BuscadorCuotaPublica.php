<?php

namespace App\Domain\Pagos;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Support\PagoCuotaToken;
use Illuminate\Support\Facades\Schema;

/**
 * Búsqueda pública de alumnos con cuota pendiente por DNI (formulario de comprobante sin cuenta).
 * Devuelve sólo nombres enmascarados y un token de un solo uso para el paso siguiente.
 */
final class BuscadorCuotaPublica
{
    /**
     * @param  list<int>  $bloqueIds
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alumnosConDeuda(int $sedeId, int $año, int $mes, array $bloqueIds, string $dni): array
    {
        $bloques = Bloque::query()
            ->whereIn('id', $bloqueIds)
            ->where('sede_id', $sedeId)
            ->where('activo', true)
            ->get();
        if ($bloques->count() !== count(array_unique($bloqueIds))) {
            return ['status' => 422, 'body' => ['error' => 'Bloques inválidos para la sede.']];
        }

        foreach ($bloqueIds as $bid) {
            if (! $this->cuotaParaBloqueMes($bid, $año, $mes)) {
                return ['status' => 422, 'body' => ['error' => 'No hay cuota cargada para uno de los bloques en ese mes.']];
            }
        }

        $alumnosPorBloque = [];
        foreach ($bloqueIds as $bid) {
            $alumnosPorBloque[$bid] = Alumno::query()
                ->where('activo', true)
                ->where(function ($q) use ($bid) {
                    $q->whereHas('bloques', fn ($sub) => $sub->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                })
                ->pluck('id')
                ->all();
        }

        if ($alumnosPorBloque === []) {
            return ['status' => 200, 'body' => ['alumnos' => []]];
        }

        $interseccion = null;
        foreach ($alumnosPorBloque as $ids) {
            $interseccion = $interseccion === null ? collect($ids) : $interseccion->intersect($ids);
        }

        $cuotasPorBloque = [];
        foreach ($bloqueIds as $bid) {
            $cuotasPorBloque[$bid] = $this->cuotaParaBloqueMes((int) $bid, $año, $mes);
        }

        $dni = preg_replace('/\D+/', '', $dni);
        if (strlen($dni) < 6) {
            return ['status' => 422, 'body' => ['error' => 'Ingresá el DNI completo (solo números).']];
        }

        $alumnos = Alumno::query()
            ->whereIn('id', $interseccion->all())
            ->where('activo', true)
            ->whereRaw("REPLACE(REPLACE(REPLACE(dni, '.', ''), '-', ''), ' ', '') = ?", [$dni])
            ->orderBy('nombre_apellido')
            ->get(['id', 'nombre_apellido']);

        $data = $alumnos->filter(function (Alumno $a) use ($cuotasPorBloque) {
            foreach ($cuotasPorBloque as $cuota) {
                if ($cuota && ! $cuota->aplicaAAlumno($a)) {
                    return false;
                }
                if ($cuota && $this->alumnoYaPagoCuota((int) $a->id, (int) $cuota->id)) {
                    return false;
                }
            }

            return true;
        })->values()->map(fn (Alumno $a) => [
            'id' => $a->id,
            // Sin cuenta no se revela el nombre: solo las iniciales para confirmar.
            'nombre_apellido' => self::enmascarar((string) $a->nombre_apellido),
            'lookup_token' => PagoCuotaToken::emitir((int) $a->id, $sedeId),
        ]);

        return ['status' => 200, 'body' => [
            'alumnos' => $data,
            'puede_multibloque' => count($bloqueIds) > 1,
            'nota_multibloque' => $data->isEmpty()
                ? 'No encontramos un alumno activo con ese DNI en los bloques elegidos (o la cuota ya figura paga).'
                : null,
        ]];
    }

    /**
     * Otros bloques del alumno donde también tiene cuota pendiente en el mes (para pagarlas juntas).
     *
     * @return array{bloques_cuotas: list<array<string, mixed>>}
     */
    public function otrosBloques(Alumno $alumno, int $sedeId, int $año, int $mes): array
    {
        $bloqueIds = $alumno->bloques()
            ->where('bloques.sede_id', $sedeId)
            ->where('bloques.activo', true)
            ->pluck('bloques.id');
        if ($alumno->bloque_id) {
            $bid = (int) $alumno->bloque_id;
            $b = Bloque::query()->whereKey($bid)->where('sede_id', $sedeId)->where('activo', true)->first();
            if ($b) {
                $bloqueIds = $bloqueIds->push($bid);
            }
        }
        $bloqueIds = $bloqueIds->unique()->values();

        $extra = [];
        foreach ($bloqueIds as $bid) {
            $cuota = $this->cuotaParaBloqueMes((int) $bid, $año, $mes);
            if (! $cuota || ! $cuota->aplicaAAlumno($alumno)) {
                continue;
            }
            $bloque = $cuota->bloque;
            if (! $bloque || (int) $bloque->sede_id !== $sedeId) {
                continue;
            }
            if ($this->alumnoYaPagoCuota((int) $alumno->id, (int) $cuota->id)) {
                continue;
            }
            $extra[] = [
                'bloque_id' => (int) $bid,
                'bloque_nombre' => $bloque->nombre,
                'cuota_id' => $cuota->id,
                'monto' => (float) $cuota->monto,
                'cuota_nombre' => $cuota->nombre,
            ];
        }

        return ['bloques_cuotas' => $extra];
    }

    /** "Juan Pérez" → "J*** P***". */
    public static function enmascarar(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)).'***', $partes));
    }

    public function cuotaParaBloqueMes(int $bloqueId, int $año, int $mes): ?Cuota
    {
        return Cuota::resolveForBloque($bloqueId, $año, $mes);
    }

    public function alumnoYaPagoCuota(int $alumnoId, int $cuotaId): bool
    {
        if (! Schema::hasTable('pago_detalles')) {
            return false;
        }

        return PagoDetalle::query()
            ->where('alumno_id', $alumnoId)
            ->where('cuota_id', $cuotaId)
            ->exists();
    }
}
