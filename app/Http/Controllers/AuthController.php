<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /**
     * Procesar login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ], [
            'username.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->filled('remember'))) {
            $request->session()->regenerate();
            
            // Asignar rol si no tiene
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
