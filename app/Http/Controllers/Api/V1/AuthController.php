<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\DispositivosPush;
use App\Domain\Personas\AccesoAlumnoService;
use App\Domain\Personas\RegistroAlumnoService;
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

        // Igual que en la web: se entra con el usuario, el correo o el DNI aunque tenga puntos ("30.123.456").
        $usuario = trim($data['username']);
        if (preg_match('/^[\d.\s-]+$/', $usuario)) {
            $usuario = (string) preg_replace('/\D+/', '', $usuario);
        }
        $user = User::query()
            ->where('username', $usuario)
            ->orWhere('email', $usuario)
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

        return response()->json($token + ['debe_cambiar_clave' => (bool) $user->debe_cambiar_clave]);
    }

    /** Registro de un alumno ya cargado por la escuela (DNI + fecha de nacimiento); devuelve la sesión. */
    public function registro(Request $request, RegistroAlumnoService $registro): JsonResponse
    {
        $datos = $request->validate(RegistroAlumnoService::reglas() + [
            'dispositivo' => ['nullable', 'string', 'max:120'],
        ], RegistroAlumnoService::mensajes());

        $user = $registro->registrar($datos);
        $user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();

        return response()->json($this->emitir($user, $datos['dispositivo'] ?? 'app') + ['usuario' => $user->username], 201);
    }

    /** El usuario cambia su contraseña (obligatorio si entró con una provisoria). Las otras sesiones se cierran. */
    public function cambiarClave(Request $request, AccesoAlumnoService $acceso): JsonResponse
    {
        $datos = $request->validate(AccesoAlumnoService::reglasCambio(), AccesoAlumnoService::mensajesCambio());
        // Se conserva la sesión de este celular (en tests con actingAs no hay token guardado: id nulo).
        $tokenId = data_get($request->user()->currentAccessToken(), 'id');
        $acceso->cambiarPropia($request->user(), $datos['actual'], $datos['password'], is_numeric($tokenId) ? (int) $tokenId : null);

        return response()->json(['ok' => true]);
    }

    /** Rota el token actual: emite uno nuevo y revoca el usado en esta llamada. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $actual = $user->currentAccessToken();
        $nuevo = $this->emitir($user, $actual?->name ?? 'app');
        $nuevoId = $user->tokens()->latest('id')->value('id');
        if (is_numeric($actual->getKey()) && $nuevoId) {
            app(DispositivosPush::class)->rotarSesion((int) $actual->getKey(), (int) $nuevoId);
        }
        $actual?->delete();

        return response()->json($nuevo);
    }

    public function logout(Request $request): JsonResponse
    {
        $sesion = $request->user()->currentAccessToken();
        // El celular deja de recibir avisos de esta cuenta.
        app(DispositivosPush::class)->cerrarSesion($request->user(), $sesion, $request->input('push_token'));
        $sesion?->delete();

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
