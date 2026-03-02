<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    /**
     * Indica si la tabla users tiene columna username (sino se usa email para login).
     */
    private function loginUsesUsername(): bool
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
            'loginUsesEmail' => !$this->loginUsesUsername(),
        ]);
    }

    /**
     * Procesar login (por username o por email si la columna username no existe aún)
     */
    public function login(Request $request)
    {
        $useUsername = $this->loginUsesUsername();

        if ($useUsername) {
            $credentials = $request->validate([
                'username' => 'required|string',
                'password' => 'required',
            ], [
                'username.required' => 'El usuario es obligatorio.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);
            $credentialKey = 'username';
            $credentialValue = $credentials['username'];
        } else {
            $credentials = $request->validate([
                'username' => 'required|email',
                'password' => 'required',
            ], [
                'username.required' => 'El correo es obligatorio.',
                'username.email' => 'Debe ser un correo válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);
            $credentialKey = 'email';
            $credentialValue = $credentials['username'];
        }

        if (Auth::attempt([$credentialKey => $credentialValue, 'password' => $credentials['password']], $request->filled('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if (!$user->hasRole('admin') && !$user->hasRole('profesor')) {
                if ($user->role === 'admin') {
                    $user->assignRole('admin');
                } else {
                    $user->assignRole('profesor');
                }
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'Usuario o contraseña incorrectos.',
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
