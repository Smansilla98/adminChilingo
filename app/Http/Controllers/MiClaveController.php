<?php

namespace App\Http\Controllers;

use App\Domain\Personas\AccesoAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Mi contraseña»: cualquiera cambia la suya; quien tiene una provisoria, antes de seguir. */
class MiClaveController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.mi-clave', ['obligatoria' => (bool) $request->user()->debe_cambiar_clave]);
    }

    public function update(Request $request, AccesoAlumnoService $acceso): RedirectResponse
    {
        $datos = $request->validate(AccesoAlumnoService::reglasCambio(), AccesoAlumnoService::mensajesCambio());
        $acceso->cambiarPropia($request->user(), $datos['actual'], $datos['password']);

        return redirect()->route('dashboard')->with('success', 'Listo, ya tenés tu contraseña nueva.');
    }
}
