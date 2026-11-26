<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\CalendarioService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    /**
     * Calendario de eventos por mes (tabla tipo restaurante-laravel).
     */
    public function index(Request $request, CalendarioService $calendario)
    {
        $year = (int) $request->get('year', now()->year);
        $month = max(1, min(12, (int) $request->get('month', now()->month)));

        return view('calendario.index', $calendario->mes($year, $month, auth()->user()));
    }

    /**
     * API de eventos para FullCalendar (mantener por si se usa en otro lado).
     */
    public function eventos(Request $request, CalendarioService $calendario)
    {
        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->startOfMonth();
        $end = $request->filled('end') ? Carbon::parse($request->end) : now()->endOfMonth()->addMonths(2);

        return response()->json($calendario->eventosApi($start, $end, auth()->user(), [
            'sede_id' => $request->filled('sede_id') ? $request->sede_id : null,
            'profesor_id' => $request->filled('profesor_id') ? $request->profesor_id : null,
        ]));
    }
}
