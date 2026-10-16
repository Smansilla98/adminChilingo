<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\CobroCuotasService;
use App\Domain\Finanzas\MercadoPagoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aviso de Mercado Pago (Webhooks e IPN). Responde 200 rápido; el pago se lee desde la
 * API de Mercado Pago, nunca del cuerpo del aviso.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoClient $mercadoPago, CobroCuotasService $cobros): JsonResponse
    {
        $tipo = $request->input('type') ?? $request->query('type') ?? $request->query('topic');
        $id = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('id') ?? '');
        if ($tipo !== 'payment' || $id === '' || ! preg_match('/^\d{1,30}$/', $id)) {
            return response()->json(['ok' => true]);
        }
        if (! $mercadoPago->firmaValida($request->header('x-signature'), $request->header('x-request-id'), $id)) {
            return response()->json(['ok' => false], 401);
        }
        $cobros->procesarAvisoMercadoPago($id);

        return response()->json(['ok' => true]);
    }
}
