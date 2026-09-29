<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MailResumenAdminService;
use App\Services\RecordatorioChatbotService;
use App\Services\WhatsAppResumenAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recordatorios del operativo. La app los dispara por la API; no abre el panel.
 */
class RecordatorioController extends Controller
{
    public function chat(Request $request, RecordatorioChatbotService $servicio): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('notificaciones.send') || $request->user()->isAdmin(), 403);
        $data = $servicio->build($request->user());
        $data['mensajes'] = collect($data['mensajes'] ?? [])->map(function ($m) {
            unset($m['accion_url']);

            return $m;
        })->values();

        return response()->json($data);
    }

    public function whatsapp(Request $request, WhatsAppResumenAdminService $servicio): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo administración puede enviar el resumen por WhatsApp.');
        $preview = $request->boolean('preview');
        if (! $preview && ! $servicio->isDisponible()) {
            return response()->json(['ok' => false, 'mensaje' => 'WhatsApp no está listo. Configurá Twilio y al menos un número destino.'], 422);
        }
        $resultado = $servicio->enviar($preview);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    public function mail(Request $request, MailResumenAdminService $servicio): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo administración puede enviar el resumen por mail.');
        $destino = trim($request->string('to')->toString());
        $resultado = $servicio->enviar($destino !== '' ? $destino : null, $request->boolean('preview'));

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }
}
