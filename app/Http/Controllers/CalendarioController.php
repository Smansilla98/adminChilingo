<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Show;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\Profesor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index()
    {
        $sedes = Sede::where('activo', true)->get();
        $profesores = Profesor::where('activo', true)->get();
        $shows = Show::proximos()->with('bloques')->take(15)->get();
        $eventos = Evento::proximos()->with('sede')->take(20)->get();
        $bloques = Bloque::where('activo', true)->with(['horarios', 'sede', 'profesor'])->orderBy('sede_id')->orderBy('nombre')->get();
        return view('calendario.index', compact('sedes', 'profesores', 'shows', 'eventos', 'bloques'));
    }

    public function eventos(Request $request)
    {
        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end = $request->filled('end') ? Carbon::parse($request->end) : now()->endOfMonth()->addMonths(2);

        $out = [];

        // Eventos (aniversarios, fiestas, rifas, shows, talleres, etc.)
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
                'className' => 'fc-event-tipo-' . str_replace(' ', '-', $evento->tipo_evento),
            ];
        }

        // Shows
        $queryShows = Show::whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
        foreach ($queryShows->with('bloques.sede')->get() as $show) {
            $title = $show->titulo;
            if ($show->convocatoria_abierta) {
                $title .= ' (convocatoria abierta)';
            } elseif ($show->bloques->isNotEmpty()) {
                $title .= ' — ' . $show->bloques->pluck('nombre')->take(2)->join(', ');
            }
            $out[] = [
                'id' => 's-' . $show->id,
                'title' => $title,
                'start' => $show->fecha->format('Y-m-d') . ($show->hora_inicio ? 'T' . $show->hora_inicio->format('H:i:s') : 'T09:00:00'),
                'end' => $show->fecha->format('Y-m-d') . ($show->hora_fin ? 'T' . $show->hora_fin->format('H:i:s') : 'T22:00:00'),
                'url' => route('shows.show', $show),
                'extendedProps' => ['tipo' => 'show'],
                'className' => 'fc-event-show',
            ];
        }

        // Horarios de bloques (recurring): generar eventos para cada semana en el rango
        $bloques = Bloque::where('activo', true)->with(['horarios', 'sede', 'profesor'])->get();
        $colores = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'];
        $idx = 0;
        foreach ($bloques as $bloque) {
            $color = $colores[$idx % count($colores)];
            $idx++;
            foreach ($bloque->horarios as $horario) {
                $diaSemana = (int) $horario->dia_semana; // 1=Lunes ... 7=Domingo (ISO)
                $horaInicio = Carbon::parse($horario->hora_inicio)->format('H:i:s');
                $horaFin = Carbon::parse($horario->hora_fin)->format('H:i:s');
                $fecha = $start->copy();
                while ($fecha->dayOfWeekIso !== $diaSemana) {
                    $fecha->addDay();
                    if ($fecha->gt($end)) break;
                }
                while ($fecha->lte($end)) {
                    $out[] = [
                        'id' => 'h-' . $bloque->id . '-' . $horario->id . '-' . $fecha->format('Y-m-d'),
                        'title' => $bloque->nombre . ' (' . ($bloque->sede?->nombre ?? '') . ')',
                        'start' => $fecha->format('Y-m-d') . 'T' . $horaInicio,
                        'end' => $fecha->format('Y-m-d') . 'T' . $horaFin,
                        'extendedProps' => [
                            'tipo' => 'horario',
                            'bloque_id' => $bloque->id,
                            'sede' => $bloque->sede?->nombre,
                            'profesor' => $bloque->profesor?->nombre,
                        ],
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'className' => 'fc-event-horario',
                    ];
                    $fecha->addWeek();
                }
            }
        }

        return response()->json($out);
    }
}
