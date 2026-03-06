<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Show;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\Profesor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class CalendarioController extends Controller
{
    /**
     * Calendario de eventos por mes (tabla tipo restaurante-laravel).
     */
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $month = max(1, min(12, $month));

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $eventsByDay = [];
        $eventos = collect();
        $shows = collect();
        $sedes = collect();
        $profesores = collect();
        $bloques = collect();

        try {
            $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
            $profesores = Profesor::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            // tablas no disponibles
        }

        try {
            $eventos = Evento::with(['sede', 'profesor'])
                ->whereBetween('fecha', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('fecha')
                ->orderBy('hora_inicio')
                ->get();

            foreach ($eventos as $evento) {
                $day = $evento->fecha->format('Y-m-d');
                if (!isset($eventsByDay[$day])) {
                    $eventsByDay[$day] = [];
                }
                $eventsByDay[$day][] = ['type' => 'evento', 'data' => $evento];
            }
        } catch (QueryException $e) {
            // tabla eventos no disponible
        }

        try {
            $shows = Show::with('bloques.sede')
                ->whereBetween('fecha', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('fecha')
                ->orderBy('hora_inicio')
                ->get();

            foreach ($shows as $show) {
                $day = $show->fecha->format('Y-m-d');
                if (!isset($eventsByDay[$day])) {
                    $eventsByDay[$day] = [];
                }
                $eventsByDay[$day][] = ['type' => 'show', 'data' => $show];
            }
        } catch (QueryException $e) {
            // tabla shows no disponible
        }

        try {
            $bloques = Bloque::where('activo', true)->with(['horarios', 'sede', 'profesor'])->orderBy('sede_id')->orderBy('nombre')->get();
        } catch (QueryException $e) {
            // tabla bloques no disponible
        }

        $firstDayOfWeek = $startDate->dayOfWeek; // 0 = domingo
        $daysInMonth = $startDate->daysInMonth;
        $prevMonth = $startDate->copy()->subMonth();
        $nextMonth = $startDate->copy()->addMonth();

        $listItems = collect();
        foreach ($eventos as $e) {
            $listItems->push((object)[
                'titulo' => $e->titulo,
                'fecha' => $e->fecha,
                'hora_inicio' => $e->hora_inicio,
                'tipo' => 'evento',
                'tipo_badge' => $e->tipo_evento ?? 'evento',
                'url' => route('eventos.show', $e),
                'model' => $e,
            ]);
        }
        foreach ($shows as $s) {
            $listItems->push((object)[
                'titulo' => $s->titulo,
                'fecha' => $s->fecha,
                'hora_inicio' => $s->hora_inicio,
                'tipo' => 'show',
                'tipo_badge' => 'show',
                'url' => route('shows.show', $s),
                'model' => $s,
            ]);
        }
        $listItems = $listItems->sortBy(function ($item) {
            $d = $item->fecha->format('Y-m-d');
            $h = $item->hora_inicio ? $item->hora_inicio->format('His') : '000000';
            return $d . $h;
        })->values();

        return view('calendario.index', compact(
            'eventsByDay',
            'listItems',
            'eventos',
            'shows',
            'bloques',
            'year',
            'month',
            'startDate',
            'endDate',
            'firstDayOfWeek',
            'daysInMonth',
            'prevMonth',
            'nextMonth',
            'sedes',
            'profesores',
            'bloques'
        ));
    }

    /**
     * API de eventos para FullCalendar (mantener por si se usa en otro lado).
     */
    public function eventos(Request $request)
    {
        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end = $request->filled('end') ? Carbon::parse($request->end) : now()->endOfMonth()->addMonths(2);
        $out = [];

        try {
            $queryEventos = Evento::with(['sede', 'profesor', 'bloque'])
                ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
            if ($request->filled('sede_id')) {
                $queryEventos->where('sede_id', $request->sede_id);
            }
            if ($request->filled('profesor_id')) {
                $queryEventos->where('profesor_id', $request->profesor_id);
            }
            foreach ($queryEventos->get() as $evento) {
                $out[] = [
                    'id' => 'e-' . $evento->id,
                    'title' => $evento->titulo,
                    'start' => $evento->fecha->format('Y-m-d') . ($evento->hora_inicio ? 'T' . $evento->hora_inicio->format('H:i:s') : ''),
                    'end' => $evento->fecha->format('Y-m-d') . ($evento->hora_fin ? 'T' . $evento->hora_fin->format('H:i:s') : ''),
                    'url' => route('eventos.show', $evento->id),
                    'extendedProps' => ['tipo' => $evento->tipo_evento, 'sede' => $evento->sede?->nombre],
                ];
            }
        } catch (QueryException $e) {
            // omitir
        }

        try {
            $queryShows = Show::whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
            foreach ($queryShows->with('bloques.sede')->get() as $show) {
                $title = $show->titulo;
                if ($show->convocatoria_abierta) {
                    $title .= ' (abierta)';
                } elseif ($show->bloques->isNotEmpty()) {
                    $title .= ' — ' . $show->bloques->pluck('nombre')->take(2)->join(', ');
                }
                $out[] = [
                    'id' => 's-' . $show->id,
                    'title' => $title,
                    'start' => $show->fecha->format('Y-m-d') . ($show->hora_inicio ? 'T' . $show->hora_inicio->format('H:i:s') : 'T09:00:00'),
                    'end' => $show->fecha->format('Y-m-d') . ($show->hora_fin ? 'T' . $show->hora_fin->format('H:i:s') : 'T22:00:00'),
                    'url' => route('shows.show', $show),
                ];
            }
        } catch (QueryException $e) {
            // omitir
        }

        return response()->json($out);
    }
}
