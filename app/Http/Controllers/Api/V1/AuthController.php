<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tokens personales (Sanctum) para la app. Un token por dispositivo, con vencimiento
 * (config sanctum.expiration) y rotación en /auth/refresh.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'dispositivo' => 'nullable|string|max:120',
        ]);

        $user = User::query()
            ->where('username', $data['username'])
            ->orWhere('email', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos.']);
        }
        if (isset($user->activo) && ! $user->activo) {
            throw ValidationException::withMessages(['username' => 'Tu cuenta está desactivada. Consultá con administración.']);
        }

        $token = $this->emitir($user, $data['dispositivo'] ?? 'app');
        $user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        Auditoria::registrar('login', $user, null, ['canal' => 'api', 'dispositivo' => $data['dispositivo'] ?? 'app']);

        return response()->json($token);
    }

    /** Rota el token actual: emite uno nuevo y revoca el usado en esta llamada. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $actual = $user->currentAccessToken();
        $nuevo = $this->emitir($user, $actual?->name ?? 'app');
        $actual?->delete();

        return response()->json($nuevo);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        if ($request->filled('push_token')) {
            $request->user()->dispositivos()->where('token', $request->input('push_token'))->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{token: string, tipo: string, expira: ?string} */
    private function emitir(User $user, string $nombre): array
    {
        $minutos = (int) config('sanctum.expiration');
        $expira = $minutos > 0 ? now()->addMinutes($minutos) : null;
        $token = $user->createToken(mb_substr($nombre, 0, 120), ['*'], $expira);

        return ['token' => $token->plainTextToken, 'tipo' => 'Bearer', 'expira' => $expira?->toIso8601String()];
    }
}
