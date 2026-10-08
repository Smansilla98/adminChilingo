<?php

namespace App\Http\Controllers;

use App\Domain\Personas\RegistroAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Registro de alumnos que la escuela ya tiene cargados (DNI + fecha de nacimiento). */
class RegistroAlumnoController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.registro-alumno');
    }

    public function store(Request $request, RegistroAlumnoService $registro): RedirectResponse
    {
        $datos = $request->validate([
            'dni' => ['required', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'dni.required' => 'Escribí tu DNI.',
            'fecha_nacimiento.required' => 'Elegí tu fecha de nacimiento.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento tiene que ser anterior a hoy.',
            'password.required' => 'Elegí una contraseña.',
            'password.min' => 'La contraseña tiene que tener al menos 6 letras o números.',
            'password.confirmed' => 'Las dos contraseñas no coinciden. Escribilas de nuevo.',
        ]);

        $user = $registro->registrar($datos);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success',
            '¡Listo, ya tenés tu cuenta! La próxima vez entrás con tu DNI ('.$user->username.') y tu contraseña.');
    }
}
