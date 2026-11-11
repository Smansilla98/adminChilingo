<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\RecuperacionClaveService;
use App\Domain\Personas\AccesoAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Olvidé mi contraseña» (web, sin sesión). */
class RecuperarClaveController extends Controller
{
    public function create(): View
    {
        return view('auth.recuperar-clave');
    }

    public function store(Request $request, RecuperacionClaveService $recuperacion): RedirectResponse
    {
        $datos = $request->validate(['usuario' => ['required', 'string', 'max:255']], ['usuario.required' => 'Escribí tu usuario, tu DNI o tu correo.']);
        $recuperacion->iniciar($datos['usuario']);

        // Misma respuesta exista o no la cuenta.
        return redirect()->route('recuperar.confirmar', ['usuario' => $datos['usuario']])
            ->with('info', RecuperacionClaveService::RESPUESTA_GENERICA);
    }

    public function confirmarCreate(Request $request): View
    {
        return view('auth.recuperar-clave-confirmar', ['usuario' => (string) $request->query('usuario', '')]);
    }

    public function confirmar(Request $request, RecuperacionClaveService $recuperacion): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:12'],
            'password' => ['required', 'string', 'confirmed', 'min:'.\App\Domain\Acceso\AjustesCuentas::CLAVE_MINIMA, 'max:255'],
        ], array_merge(AccesoAlumnoService::mensajesCambio(), ['codigo.required' => 'Escribí el código que te mandamos.']));

        $recuperacion->confirmar($datos['usuario'], $datos['codigo'], $datos['password']);

        return redirect()->route('login')->with('success', 'Listo, ya podés entrar con tu nueva contraseña.');
    }
}
