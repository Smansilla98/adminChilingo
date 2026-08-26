<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\ObservacionPedagogica;
use App\Models\PagoDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EspacioAlumnoService
{
    /**
     * @return array{
     *   proximaClase: ?array,
     *   asistencias: Collection,
     *   proximoPaso: ?ObservacionPedagogica,
     *   eventos: Collection,
     *   estadoCuota: ?array
     * }
     */
    public function armar(?Alumno $alumno): array
    {
        $vacio = [
            'proximaClase' => null,
            'asistencias' => collect(),
            'proximoPaso' => null,
            'eventos' => collect(),
            'estadoCuota' => null,
        ];
        if (! $alumno) {
            return $vacio;
        }

        $alumno->loadMissing(['bloques.horarios', 'bloques.sede', 'bloque.horarios', 'bloque.sede', 'sede']);
        $diaIso = (int) now()->isoWeekday();
        $proximaClase = null;
        foreach ($alumno->bloques->isNotEmpty() ? $alumno->bloques : collect([$alumno->bloque])->filter() as $bloque) {
            $h = $bloque->horarios?->firstWhere('dia_semana', $diaIso)
                ?? $bloque->horarios?->sortBy('dia_semana')->first();
            if ($h && $proximaClase === null) {
                $proximaClase = [
                    'bloque' => $bloque->nombre,
                    'sede' => $bloque->sede->nombre ?? $alumno->sede->nombre ?? null,
                    'dia' => BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? '',
                    'hora' => $h->hora_inicio ? substr((string) $h->hora_inicio, 0, 5) : null,
                    'es_hoy' => (int) $h->dia_semana === $diaIso,
                ];
            }
            if ($h && (int) $h->dia_semana === $diaIso) {
                $proximaClase = [
                    'bloque' => $bloque->nombre,
                    'sede' => $bloque->sede->nombre ?? $alumno->sede->nombre ?? null,
                    'dia' => BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? '',
                    'hora' => $h->hora_inicio ? substr((string) $h->hora_inicio, 0, 5) : null,
                    'es_hoy' => true,
                ];
                break;
            }
        }

        $asistencias = Asistencia::query()
            ->where('alumno_id', $alumno->id)
            ->orderByDesc('fecha')
            ->limit(6)
            ->get();

        $proximoPaso = null;
        if (Schema::hasTable('observaciones_pedagogicas')) {
            $q = ObservacionPedagogica::query()
                ->where('alumno_id', $alumno->id)
                ->orderByDesc('fecha')
                ->orderByDesc('id');
            if (Schema::hasColumn('observaciones_pedagogicas', 'visible_alumno')) {
                $q->where('visible_alumno', true);
            }
            $proximoPaso = $q->first();
        }

        $eventosQ = Evento::query()->proximos()->orderBy('fecha');
        $sedeIds = $alumno->bloques->pluck('sede_id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($alumno->sede_id) {
            $sedeIds[] = (int) $alumno->sede_id;
        }
        $sedeIds = array_values(array_unique($sedeIds));
        if ($sedeIds !== []) {
            $eventosQ->where(function ($q) use ($sedeIds) {
                $q->whereIn('sede_id', $sedeIds)
                    ->orWhereNull('sede_id')
                    ->orWhereHas('bloque', fn ($b) => $b->whereIn('sede_id', $sedeIds));
            });
        }
        $eventos = $eventosQ->limit(4)->get();

        $estadoCuota = $this->estadoCuotaMes($alumno);

        return compact('proximaClase', 'asistencias', 'proximoPaso', 'eventos', 'estadoCuota');
    }

    /**
     * Resumen de cuota del mes actual solo para este alumno (sin listar compañeros).
     *
     * @return array{estado: string, label: string, hint: string, monto: ?float, periodo: string, pagar_url: string}|null
     */
    public function estadoCuotaMes(Alumno $alumno): ?array
    {
        if (! Schema::hasTable('cuotas')) {
            return null;
        }

        $mes = (int) now()->month;
        $anio = (int) now()->year;
        $periodo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$anio;

        $pagarUrl = route('comprobante-cuota-public.create', array_filter([
            'dni' => $alumno->dni,
            'sede_id' => $alumno->sede_id,
        ]));

        try {
            $cuotas = Cuota::query()
                ->with('alumnos:id')
                ->where('activo', true)
                ->where('mes', $mes)
                ->where('año', $anio)
                ->get()
                ->filter(fn (Cuota $c) => $c->aplicaAAlumno($alumno))
                ->values();
        } catch (\Throwable) {
            return null;
        }

        if ($cuotas->isEmpty()) {
            return [
                'estado' => 'sin_cuota',
                'label' => 'Sin cuota cargada este mes',
                'hint' => 'Cuando la escuela emita la cuota, vas a ver el estado acá.',
                'monto' => null,
                'periodo' => $periodo,
                'pagar_url' => $pagarUrl,
            ];
        }

        $pendientes = [];
        $hoy = Carbon::today();
        foreach ($cuotas as $cuota) {
            $pagado = Schema::hasTable('pago_detalles')
                && PagoDetalle::query()->where('alumno_id', $alumno->id)->where('cuota_id', $cuota->id)->exists();
            if ($pagado) {
                continue;
            }
            $pendientes[] = $cuota;
        }

        if ($pendientes === []) {
            return [
                'estado' => 'al_dia',
                'label' => 'Cuota al día',
                'hint' => 'Este mes ya figura como abonado.',
                'monto' => null,
                'periodo' => $periodo,
                'pagar_url' => $pagarUrl,
            ];
        }

        if (Schema::hasTable('comprobantes_cuota_alumnos')) {
            $enRevision = ComprobanteCuotaAlumno::query()
                ->where('alumno_id', $alumno->id)
                ->where('estado', 'pendiente')
                ->where('año', $anio)
                ->where('mes', $mes)
                ->exists();
            if ($enRevision) {
                return [
                    'estado' => 'en_revision',
                    'label' => 'Comprobante en revisión',
                    'hint' => 'Ya lo enviaron. Administración lo está revisando.',
                    'monto' => (float) $pendientes[0]->monto,
                    'periodo' => $periodo,
                    'pagar_url' => $pagarUrl,
                ];
            }
        }

        $primera = $pendientes[0];
        $vencida = $primera->fecha_vencimiento && $primera->fecha_vencimiento->lt($hoy);

        return [
            'estado' => $vencida ? 'vencida' : 'pendiente',
            'label' => $vencida ? 'Cuota vencida' : 'Cuota pendiente',
            'hint' => $primera->nombre.' · $'.number_format((float) $primera->monto, 0, ',', '.'),
            'monto' => (float) $primera->monto,
            'periodo' => $periodo,
            'pagar_url' => $pagarUrl,
        ];
    }
}
