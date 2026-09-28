<?php

namespace App\Domain\Operativo;

use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\FacturacionMensual;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist del cierre de mes (toda la escuela): asistencias cargadas, comprobantes
 * por revisar, cuotas emitidas y facturación. Web y API muestran lo mismo.
 */
class CierreMesService
{
    /**
     * @return list<array{clave: string, titulo: string, ok: ?bool, detalle: string}>
     */
    public function checklist(int $mes, int $anio): array
    {
        $bloquesSinAsist = 0;
        if (Schema::hasTable('bloques')) {
            foreach (Bloque::query()->where('activo', true)->pluck('id') as $bloqueId) {
                $hay = Schema::hasTable('asistencias') && Asistencia::query()->where('bloque_id', $bloqueId)->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->exists();
                if (! $hay) {
                    $bloquesSinAsist++;
                }
            }
        }
        $compPend = Schema::hasTable('comprobantes_cuota_alumnos') ? ComprobanteCuotaAlumno::query()->where('estado', 'pendiente')->count() : 0;
        $cuotasMes = Schema::hasTable('cuotas') ? Cuota::query()->where('activo', true)->where('mes', $mes)->where('año', $anio)->count() : 0;
        $facturas = Schema::hasTable('facturacion_mensual') ? FacturacionMensual::query()->where('mes', $mes)->where('año', $anio)->count() : 0;

        return [
            [
                'clave' => 'asistencias',
                'titulo' => 'Asistencias del mes',
                'ok' => $bloquesSinAsist === 0,
                'detalle' => $bloquesSinAsist === 0 ? 'Todos los bloques activos tienen al menos un registro.' : "{$bloquesSinAsist} bloque(s) sin ninguna asistencia cargada.",
            ],
            [
                'clave' => 'comprobantes',
                'titulo' => 'Comprobantes por revisar',
                'ok' => $compPend === 0,
                'detalle' => $compPend === 0 ? 'No hay comprobantes pendientes.' : "{$compPend} pendiente(s).",
            ],
            [
                'clave' => 'cuotas',
                'titulo' => 'Cuotas emitidas del mes',
                'ok' => $cuotasMes > 0,
                'detalle' => $cuotasMes > 0 ? "{$cuotasMes} cuota(s) activas." : 'No hay cuotas cargadas para este mes.',
            ],
            [
                'clave' => 'facturacion',
                'titulo' => 'Facturación mensual',
                'ok' => null,
                'detalle' => $facturas > 0 ? "{$facturas} carga(s) de facturación del mes. Revisá que sean coherentes con los cobros." : 'Revisá que el mes esté generado y coherente con los cobros.',
            ],
        ];
    }
}
