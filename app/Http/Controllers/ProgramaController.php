<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use Illuminate\Database\QueryException;

class ProgramaController extends Controller
{
    /**
     * Programa oficial de la escuela: toques por año (consulta).
     * Si la tabla no existe (migraciones pendientes), muestra la vista vacía.
     */
    public function index()
    {
        try {
            $porAño = ProgramaRitmo::orderBy('año')->orderBy('orden')->get()->groupBy('año');
            $años = ProgramaRitmo::años();
        } catch (QueryException $e) {
            $porAño = collect();
            $años = [];
        }

        return view('programa.index', compact('porAño', 'años'));
    }
}
