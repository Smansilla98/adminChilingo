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
use Illuminate\Support\Collection;

class CalendarioController extends Controller
{
    /**
     * Día de la semana en Carbon (0=dom … 6=sáb) a partir de bloque_horarios.dia_semana (1=lun … 7=dom).
     */
    protected static function carbonDowFromBloqueDia(int $diaSemana): int
    {
        return $diaSemana === 7 ? 0 : $diaSemana;
    }

    /**
     * Bloques activos con horarios, acotados al usuario (profesor solo los suyos).
     *
     * @return Collection<int, Bloque>
     */
    protected function bloquesParaCalendario(): Collection
    {
        $user = auth()->user();
        $query = Bloque::query()
            ->where('activo', true)
            ->with(['horarios', 'sede', 'profesor'])
            ->whereHas('horarios');

        if ($user && $user->isProfesor() && ! $user->isAdmin()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $query->orderBy('sede_id')->orderBy('nombre')->get();
    }

    protected function urlBloqueDesdeCalendario(Bloque $bloque): string
    {
        $user = auth()->user();
        if ($user && $user->isAdmin()) {
            return route('bloques.edit', $bloque);
        }

        return route('profesor.bloques');
    }

    /**
     * @param  array<string, array<int, array{type: string, data: mixed}>>  $eventsByDay
     */
    protected function ordenarItemsPorDia(array &$eventsByDay): void
    {
        $hora = function (array $item): string {
            if ($item['type'] === 'evento') {
                $h = $item['data']->hora_inicio;

                return $h ? $h->format('H:i:s') : '00:00:00';
            }
            if ($item['type'] === 'show') {
                $h = $item['data']->hora_inicio;

                return $h ? $h->format('H:i:s') : '00:00:00';
            }
            if ($item['type'] === 'bloque_taller') {
                $h = $item['data']['horario']->hora_inicio;
                if ($h instanceof Carbon) {
                    return $h->format('H:i:s');
                }

                return $h ? Carbon::parse($h)->format('H:i:s') : '00:00:00';
            }

            return '99:99:99';
        };
        $titulo = function (array $item): string {
            if ($item['type'] === 'evento') {
                return mb_strtolower($item['data']->titulo ?? '');
            }
            if ($item['type'] === 'show') {
                return mb_strtolower($item['data']->titulo ?? '');
            }
            if ($item['type'] === 'bloque_taller') {
                return mb_strtolower($item['data']['bloque']->nombre ?? '');
            }

            return '';
        };
        foreach (array_keys($eventsByDay) as $day) {
            usort($eventsByDay[$day], function (array $a, array $b) use ($hora, $titulo): int {
                $c = strcmp($hora($a), $hora($b));
                if ($c !== 0) {
                    return $c;
                }

                return strcmp($titulo($a), $titulo($b));
            });
        }
    }

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
        $daysInMonth = $startDate->daysInMonth;
        $firstDayOfWeek = $startDate->dayOfWeek;

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
            $bloques = $this->bloquesParaCalendario();
        } catch (QueryException $e) {
            $bloques = collect();
        }

        try {
            foreach ($bloques as $bloque) {
                foreach ($bloque->horarios as $horario) {
                    $carbonDow = self::carbonDowFromBloqueDia((int) $horario->dia_semana);
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $occDate = $startDate->copy()->addDays($d - 1);
                        if ((int) $occDate->dayOfWeek !== $carbonDow) {
                            continue;
                        }
                        $dayKey = $occDate->format('Y-m-d');
                        if (! isset($eventsByDay[$dayKey])) {
                            $eventsByDay[$dayKey] = [];
                        }
                        $eventsByDay[$dayKey][] = [
                            'type' => 'bloque_taller',
                            'data' => [
                                'bloque' => $bloque,
                                'horario' => $horario,
                                'url' => $this->urlBloqueDesdeCalendario($bloque),
                            ],
                        ];
                    }
                }
            }
        } catch (QueryException $e) {
            // omitir
        }

        $this->ordenarItemsPorDia($eventsByDay);

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
        foreach ($bloques as $bloque) {
            foreach ($bloque->horarios as $horario) {
                $carbonDow = self::carbonDowFromBloqueDia((int) $horario->dia_semana);
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $occDate = $startDate->copy()->addDays($d - 1);
                    if ((int) $occDate->dayOfWeek !== $carbonDow) {
                        continue;
                    }
                    $hi = $horario->hora_inicio;
                    $listItems->push((object) [
                        'titulo' => $bloque->nombre,
                        'fecha' => $occDate->copy()->startOfDay(),
                        'hora_inicio' => $hi,
                        'tipo' => 'bloque_taller',
                        'tipo_badge' => 'taller fijo',
                        'url' => $this->urlBloqueDesdeCalendario($bloque),
                        'model' => null,
                        'bloque' => $bloque,
                        'horario' => $horario,
                    ]);
                }
            }
        }
        $listItems = $listItems->sortBy(function ($item) {
            $d = $item->fecha->format('Y-m-d');
            $h = $item->hora_inicio ? $item->hora_inicio->format('His') : '000000';

            return $d.$h;
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
            'profesores'
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

        try {
            $bloquesApi = $this->bloquesParaCalendario();
            $cursor = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();
            while ($cursor <= $endDay) {
                foreach ($bloquesApi as $bloque) {
                    foreach ($bloque->horarios as $horario) {
                        $cd = self::carbonDowFromBloqueDia((int) $horario->dia_semana);
                        if ((int) $cursor->dayOfWeek !== $cd) {
                            continue;
                        }
                        $dayStr = $cursor->format('Y-m-d');
                        $t0 = $horario->hora_inicio;
                        $t1 = $horario->hora_fin;
                        $startT = $t0 instanceof Carbon ? $t0->format('H:i:s') : Carbon::parse($t0)->format('H:i:s');
                        $endT = $t1 instanceof Carbon ? $t1->format('H:i:s') : Carbon::parse($t1)->format('H:i:s');
                        $out[] = [
                            'id' => 't-'.$bloque->id.'-'.$horario->id.'-'.$dayStr,
                            'title' => 'Taller: '.$bloque->nombre,
                            'start' => $dayStr.'T'.$startT,
                            'end' => $dayStr.'T'.$endT,
                            'url' => $this->urlBloqueDesdeCalendario($bloque),
                            'backgroundColor' => '#198754',
                            'borderColor' => '#146c43',
                            'extendedProps' => ['tipo' => 'taller_fijo', 'bloque' => $bloque->nombre],
                        ];
                    }
                }
                $cursor->addDay();
            }
        } catch (QueryException $e) {
            // omitir
        }

        return response()->json($out);
    }
}
