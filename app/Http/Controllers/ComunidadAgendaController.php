<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Show;
use Illuminate\Support\Facades\Schema;

class ComunidadAgendaController extends Controller
{
    public function index()
    {
        $eventos = collect();
        $shows = collect();

        if (Schema::hasTable('eventos')) {
            $eventos = Evento::query()->proximos()->with('sede')->limit(20)->get();
        }
        if (Schema::hasTable('shows')) {
            $shows = Show::query()->proximos()->limit(12)->get();
        }

        return view('comunidad.agenda', compact('eventos', 'shows'));
    }
}
