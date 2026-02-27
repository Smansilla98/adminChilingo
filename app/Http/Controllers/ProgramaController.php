<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;

class ProgramaController extends Controller
{
    /**
     * Programa oficial de la escuela: toques por año (consulta).
     */
    public function index()
    {
        $porAño = ProgramaRitmo::orderBy('año')->orderBy('orden')->get()->groupBy('año');
        $años = ProgramaRitmo::años();
        return view('programa.index', compact('porAño', 'años'));
    }
}
