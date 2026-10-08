<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    /**
     * Indica si la tabla users tiene la columna username (sino se usa email para login/registro).
     */
    private function hasUsernameColumn(): bool
    {
        try {
            return Schema::hasColumn('users', 'username');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mostrar formulario de login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'loginUsesEmail' => ! $this->hasUsernameColumn(),
        ]);
    }

    /**
     * Procesar login (usuario o correo según exista la columna username)
     */
    public function login(Request $request)
    {
        $hasUsername = $this->hasUsernameColumn();

        if ($hasUsername) {
            $credentials = $request->validate([
                'username' => 'required|string',
                'password' => 'required',
            ], [
                'username.required' => 'El usuario es obligatorio.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);
            $credentialKey = 'username';
            $credentialValue = trim($credentials['username']);
            // Se puede entrar con el correo o con el DNI aunque tenga puntos ("30.123.456").
            if (str_contains($credentialValue, '@')) {
                $credentialKey = 'email';
            } elseif (preg_match('/^[\d.\s-]+$/', $credentialValue)) {
                $credentialValue = preg_replace('/\D+/', '', $credentialValue);
            }
        } else {
            $credentials = $request->validate([
                'username' => 'required|email',
                'password' => 'required',
            ], [
                'username.required' => 'El correo es obligatorio.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);
            $credentialKey = 'email';
            $credentialValue = $credentials['username'];
        }

        if (Auth::attempt([$credentialKey => $credentialValue, 'password' => $credentials['password']], $request->filled('remember'))) {
            $user = Auth::user();
            if (isset($user->activo) && ! $user->activo) {
                Auth::logout();

                return back()->withErrors(['username' => 'Tu cuenta está desactivada. Consultá con administración.'])->onlyInput('username');
            }
            $request->session()->regenerate();
            $user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();

            // Los permisos salen de funciones y asignaciones (App\Domain\Acceso): ya no se
            // asigna un rol por defecto al ingresar.
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'El usuario o la contraseña no son correctos. Revisalos y probá de nuevo. Si no te acordás la contraseña, pedile a tu profe o a administración que te la cambie.',
        ])->onlyInput('username');
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
