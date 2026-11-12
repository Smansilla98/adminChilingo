<?php

namespace App\Http\Controllers;

use App\Domain\Personas\PreinscripcionService;
use App\Models\Preinscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bandeja de preinscripciones (web): lista de espera, aprobar o rechazar. */
class PreinscripcionController extends Controller
{
    public function index(Request $request): View
    {
        $estado = in_array($request->query('estado'), [Preinscripcion::PENDIENTE, Preinscripcion::APROBADA, Preinscripcion::RECHAZADA], true)
            ? $request->query('estado') : Preinscripcion::PENDIENTE;

        $preinscripciones = Preinscripcion::query()->with(['sede', 'bloque'])
            ->where('estado', $estado)->latest('id')->paginate(30)->withQueryString();

        return view('preinscripciones.index', ['preinscripciones' => $preinscripciones, 'estado' => $estado]);
    }

    public function show(Preinscripcion $preinscripcion): View
    {
        return view('preinscripciones.show', ['p' => $preinscripcion->load(['sede', 'bloque'])]);
    }

    public function aprobar(Request $request, Preinscripcion $preinscripcion, PreinscripcionService $servicio): RedirectResponse
    {
        $datos = $request->validate(['bloque_id' => ['nullable', 'integer', 'exists:bloques,id']]);
        $alumno = $servicio->aprobar($preinscripcion, $request->user(), $datos['bloque_id'] ?? null);

        return redirect()->route('preinscripciones.index', ['estado' => 'aprobada'])
            ->with('success', 'Inscripción aprobada. '.$alumno->nombre_apellido.' ya es alumno de la sede.');
    }

    public function rechazar(Request $request, Preinscripcion $preinscripcion, PreinscripcionService $servicio): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $servicio->rechazar($preinscripcion, $request->user(), trim((string) ($datos['motivo'] ?? '')));

        return redirect()->route('preinscripciones.index')->with('success', 'Preinscripción rechazada. Se avisó a la persona.');
    }
}
