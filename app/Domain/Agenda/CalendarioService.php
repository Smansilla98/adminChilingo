<?php

namespace App\Domain\Agenda;

use App\Models\Bloque;
use App\Models\Evento;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\Show;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Datos del calendario: vista mensual (eventos, shows y talleres fijos de los bloques)
 * y eventos para la API de FullCalendar. Acotado a lo que el usuario puede ver.
 */
class CalendarioService
{
    /**
     * Día de la semana en Carbon (0=dom … 6=sáb) a partir de bloque_horarios.dia_semana (1=lun … 7=dom).
     */
    public static function carbonDowFromBloqueDia(int $diaSemana): int
    {
        return $diaSemana === 7 ? 0 : $diaSemana;
    }

    /**
     * Variables de la vista mensual del calendario.
     *
     * @return array<string, mixed>
     */
    public function mes(int $year, int $month, User $user): array
    {
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
                ->tap(fn ($q) => $user->acceso()->alcance('calendario.view')->aplicarEventos($q))
                ->orderBy('fecha')
                ->orderBy('hora_inicio')
                ->get();

            foreach ($eventos as $evento) {
                $eventsByDay[$evento->fecha->format('Y-m-d')][] = ['type' => 'evento', 'data' => $evento];
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
                $eventsByDay[$show->fecha->format('Y-m-d')][] = ['type' => 'show', 'data' => $show];
            }
        } catch (QueryException $e) {
            // tabla shows no disponible
        }

        try {
            $bloques = $this->bloquesParaCalendario($user);
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
                        $eventsByDay[$occDate->format('Y-m-d')][] = [
                            'type' => 'bloque_taller',
                            'data' => [
                                'bloque' => $bloque,
                                'horario' => $horario,
                                'url' => $this->urlBloqueDesdeCalendario($bloque, $user),
                            ],
                        ];
                    }
                }
            }
        } catch (QueryException $e) {
            // omitir
        }

        $this->ordenarItemsPorDia($eventsByDay);

        return [
            'eventsByDay' => $eventsByDay,
            'listItems' => $this->listado($eventos, $shows, $bloques, $startDate, $user),
            'eventos' => $eventos,
            'shows' => $shows,
            'bloques' => $bloques,
            'year' => $year,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'firstDayOfWeek' => $firstDayOfWeek,
            'daysInMonth' => $daysInMonth,
            'prevMonth' => $startDate->copy()->subMonth(),
            'nextMonth' => $startDate->copy()->addMonth(),
            'sedes' => $sedes,
            'profesores' => $profesores,
        ];
    }

    /**
     * Eventos, shows y talleres fijos entre dos fechas, en el formato que usa FullCalendar.
     *
     * @param  array{sede_id?: ?string, profesor_id?: ?string}  $filtros
     * @return list<array<string, mixed>>
     */
    public function eventosApi(Carbon $start, Carbon $end, User $user, array $filtros = []): array
    {
        $out = [];

        try {
            $queryEventos = Evento::with(['sede', 'profesor', 'bloque'])
                ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
            // Eventos de la escuela, de sus sedes y de sus bloques.
            $user->acceso()->alcance('calendario.view')->aplicarEventos($queryEventos);
            if (($filtros['sede_id'] ?? null) !== null) {
                $queryEventos->where('sede_id', $filtros['sede_id']);
            }
            if (($filtros['profesor_id'] ?? null) !== null) {
                $queryEventos->where('profesor_id', $filtros['profesor_id']);
            }
            foreach ($queryEventos->get() as $evento) {
                $out[] = [
                    'id' => 'e-'.$evento->id,
                    'title' => $evento->titulo,
                    'start' => $evento->fecha->format('Y-m-d').($evento->hora_inicio ? 'T'.$evento->hora_inicio->format('H:i:s') : ''),
                    'end' => $evento->fecha->format('Y-m-d').($evento->hora_fin ? 'T'.$evento->hora_fin->format('H:i:s') : ''),
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
                    $title .= ' — '.$show->bloques->pluck('nombre')->take(2)->join(', ');
                }
                $out[] = [
                    'id' => 's-'.$show->id,
                    'title' => $title,
                    'start' => $show->fecha->format('Y-m-d').($show->hora_inicio ? 'T'.$show->hora_inicio->format('H:i:s') : 'T09:00:00'),
                    'end' => $show->fecha->format('Y-m-d').($show->hora_fin ? 'T'.$show->hora_fin->format('H:i:s') : 'T22:00:00'),
                    'url' => route('shows.show', $show),
                ];
            }
        } catch (QueryException $e) {
            // omitir
        }

        try {
            $bloquesApi = $this->bloquesParaCalendario($user);
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
                            'url' => $this->urlBloqueDesdeCalendario($bloque, $user),
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

        return $out;
    }

    /**
     * Bloques activos con horarios, acotados al usuario (profesor solo los suyos).
     *
     * @return Collection<int, Bloque>
     */
    private function bloquesParaCalendario(User $user): Collection
    {
        $query = Bloque::query()
            ->where('activo', true)
            ->with(['horarios', 'sede', 'profesor'])
            ->whereHas('horarios');

        // Clases de los bloques/sedes donde la persona participa (docente, alumno, coordinación).
        $user->acceso()->alcance('calendario.view')->aplicarBloques($query);

        return $query->orderBy('sede_id')->orderBy('nombre')->get();
    }

    private function urlBloqueDesdeCalendario(Bloque $bloque, User $user): string
    {
        if ($user->can('update', $bloque)) {
            return route('bloques.edit', $bloque);
        }

        return route('profesor.bloques');
    }

    /** Lista plana del mes (eventos, shows y talleres) ordenada por fecha y hora. */
    private function listado(Collection $eventos, Collection $shows, Collection $bloques, Carbon $startDate, User $user): Collection
    {
        $daysInMonth = $startDate->daysInMonth;
        $listItems = collect();
        foreach ($eventos as $e) {
            $listItems->push((object) [
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
            $listItems->push((object) [
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
                    $listItems->push((object) [
                        'titulo' => $bloque->nombre,
                        'fecha' => $occDate->copy()->startOfDay(),
                        'hora_inicio' => $horario->hora_inicio,
                        'tipo' => 'bloque_taller',
                        'tipo_badge' => 'taller fijo',
                        'url' => $this->urlBloqueDesdeCalendario($bloque, $user),
                        'model' => null,
                        'bloque' => $bloque,
                        'horario' => $horario,
                    ]);
                }
            }
        }

        return $listItems->sortBy(function ($item) {
            $d = $item->fecha->format('Y-m-d');
            $h = $item->hora_inicio ? $item->hora_inicio->format('His') : '000000';

            return $d.$h;
        })->values();
    }

    /** Orden de cada día: hora de inicio y luego título. */
    private function ordenarItemsPorDia(array &$eventsByDay): void
    {
        $hora = function (array $item): string {
            if ($item['type'] === 'evento' || $item['type'] === 'show') {
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
            if ($item['type'] === 'evento' || $item['type'] === 'show') {
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
}
