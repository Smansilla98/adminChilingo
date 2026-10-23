<?php

namespace App\Http\Controllers;

use App\Support\EstadoSistema;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstadoSistemaController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('estado-sistema', ['chequeos' => EstadoSistema::chequeos($request)]);
    }
}
