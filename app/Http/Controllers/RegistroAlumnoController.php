<?php

namespace App\Http\Controllers;

use App\Domain\Personas\RegistroAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Registro de alumnos que la escuela ya tiene cargados (DNI + fecha de nacimiento). */
class RegistroAlumnoController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.registro-alumno', ['requiereCodigo' => \App\Domain\Acceso\AjustesCuentas::registroRequiereCodigo()]);
    }

    public function store(Request $request, RegistroAlumnoService $registro): RedirectResponse
    {
        $datos = $request->validate(RegistroAlumnoService::reglas(), RegistroAlumnoService::mensajes());

        $user = $registro->registrar($datos);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success',
            '¡Listo, ya tenés tu cuenta! La próxima vez entrás con tu DNI ('.$user->username.') y tu contraseña.');
    }
}
