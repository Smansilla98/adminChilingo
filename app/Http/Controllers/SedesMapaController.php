<?php

namespace App\Http\Controllers;

use App\Support\SedesPublicas;
use Illuminate\View\View;

/** Mapa público de las sedes de la escuela. */
class SedesMapaController extends Controller
{
    public function __invoke(): View
    {
        return view('programa.sedes', ['sedes' => SedesPublicas::listar()]);
    }
}
