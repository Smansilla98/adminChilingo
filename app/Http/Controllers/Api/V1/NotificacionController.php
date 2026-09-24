<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $avisos = $user->notifications()->latest()->limit(50)->get();

        return response()->json([
            'no_leidas' => $user->unreadNotifications()->count(),
            'data' => $avisos->map(fn ($n) => [
                'id' => $n->id,
                'tipo' => $n->data['tipo'] ?? class_basename($n->type),
                'titulo' => $n->data['titulo'] ?? '',
                'mensaje' => $n->data['mensaje'] ?? '',
                'enlace' => $n->data['enlace'] ?? null,
                'leida' => $n->read_at !== null,
                'fecha' => $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function leer(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    /** Registra el token de push del dispositivo (Expo). Un token pertenece a una sola cuenta. */
    public function registrarDispositivo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^(ExponentPushToken|ExpoPushToken)\[.+\]$/'],
            'plataforma' => 'nullable|in:android,ios',
            'nombre' => 'nullable|string|max:120',
        ]);
        Dispositivo::query()->updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'plataforma' => $data['plataforma'] ?? null, 'nombre' => $data['nombre'] ?? null, 'ultimo_uso_at' => now()]
        );

        return response()->json(['ok' => true]);
    }

    public function quitarDispositivo(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:255']);
        $request->user()->dispositivos()->where('token', $request->input('token'))->delete();

        return response()->json(['ok' => true]);
    }
}
