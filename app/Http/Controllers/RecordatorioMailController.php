<?php

namespace App\Http\Controllers;

use App\Services\MailResumenAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordatorioMailController extends Controller
{
    public function enviar(Request $request, MailResumenAdminService $servicio): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Solo los administradores pueden enviar el resumen por mail.',
            ], 403);
        }

        $dryRun = $request->boolean('preview');
        $toOverride = $request->string('to')->toString();
        $toOverride = trim($toOverride) !== '' ? $toOverride : null;

        $resultado = $servicio->enviar($toOverride, $dryRun);

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }
}

