<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

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
            'loginUsesEmail' => !$this->hasUsernameColumn(),
        ]);
    }

    /**
     * Mostrar formulario de registro
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register', [
            'hasUsernameColumn' => $this->hasUsernameColumn(),
        ]);
    }

    /**
     * Procesar registro de nuevo usuario (compatible con y sin columna username)
     */
    public function register(Request $request)
    {
        $hasUsername = $this->hasUsernameColumn();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
        $messages = [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];

        if ($hasUsername) {
            $rules['username'] = 'required|string|max:80|unique:users,username';
            $messages['username.required'] = 'El usuario es obligatorio.';
            $messages['username.unique'] = 'Ese nombre de usuario ya está en uso.';
        }

        $validated = $request->validate($rules, $messages);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'profesor',
        ];
        if ($hasUsername) {
            $data['username'] = $validated['username'];
        }

        $user = User::create($data);

        Role::firstOrCreate(['name' => 'profesor']);
        $user->assignRole('profesor');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
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
            $credentialValue = $credentials['username'];
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
