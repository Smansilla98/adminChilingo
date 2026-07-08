<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppResumenAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordatorioWhatsAppController extends Controller
{
    public function enviar(Request $request, WhatsAppResumenAdminService $servicio): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Solo los administradores pueden enviar el resumen por WhatsApp.',
            ], 403);
        }

        $dryRun = $request->boolean('preview');

        if (! $dryRun && ! $servicio->isDisponible()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'WhatsApp no está listo. Configurá Twilio y al menos un número destino.',
            ], 422);
        }

        $resultado = $servicio->enviar($dryRun);

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }
}
