<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\PresentadorAcceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Estoy trabajando como…": cambia la vista por defecto, no la identidad ni los permisos.
 */
class ContextoController extends Controller
{
    public function cambiar(Request $request, PresentadorAcceso $presentador): RedirectResponse
    {
        $clave = (string) $request->input('contexto');
        $validas = array_column($presentador->contextos($request->user()->acceso()), 'clave');

        if ($clave === '' || ! in_array($clave, $validas, true)) {
            $request->session()->forget('contexto');
        } else {
            $request->session()->put('contexto', $clave);
        }

        return redirect()->route('dashboard');
    }
}
