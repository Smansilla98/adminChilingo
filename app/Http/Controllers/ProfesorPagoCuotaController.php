<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\PagosDocenteService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProfesorPagoCuotaController extends Controller
{
    /**
     * Líneas de pago (alumno + cuota) visibles para el profesor: alumnos de sus bloques y cuotas de esos bloques,
     * cuotas generales o por sede que apliquen al contexto de sus bloques.
     */
    public function index(Request $request, PagosDocenteService $servicio)
    {
        $detalles = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30);
        $alumnosFiltro = collect();

        $profesor = auth()->user()?->profesor;
        if (! $profesor) {
            return view('profesor.pagos-cuotas', compact('detalles', 'alumnosFiltro'));
        }

        try {
            $q = $servicio->lineas($profesor, $request->only(['alumno_id', 'desde', 'hasta']));
            if ($q) {
                $detalles = $q->paginate(30)->withQueryString();
                $alumnosFiltro = $servicio->alumnos($profesor);
            }
        } catch (QueryException $e) {
            report($e);
        }

        return view('profesor.pagos-cuotas', compact('detalles', 'alumnosFiltro'));
    }
}
