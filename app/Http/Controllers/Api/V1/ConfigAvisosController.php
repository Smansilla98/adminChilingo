<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\CobroCuotasService;
use App\Domain\Notificaciones\Avisos;
use App\Domain\Notificaciones\ConfigAvisos;
use App\Domain\Notificaciones\NotificacionService;
use App\Http\Controllers\ConfigAvisosController as Web;
use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Configuración de avisos automáticos y datos para pagar, desde la app (misma lógica que la web). */
class ConfigAvisosController extends Controller
{
    public function show(CobroCuotasService $cobros): JsonResponse
    {
        return response()->json([
            'avisos' => ConfigAvisos::todos(),
            'canales' => Web::CANALES,
            'variables' => ConfigAvisos::VARIABLES,
            'cobro' => Ajuste::leer('cobro'),
            'cobro_efectivo' => $cobros->datosTransferencia() ?? (object) [],
            'mercado_pago' => $cobros->mercadoPagoDisponible(),
            'push' => NotificacionService::estadoPush(),
        ]);
    }

    public function update(Request $request, CobroCuotasService $cobros): JsonResponse
    {
        $datos = $request->validate(Web::reglas());
        if (array_key_exists('avisos', $datos)) {
            ConfigAvisos::guardar($datos['avisos'] ?? [], $request->user()->id);
        }
        if (array_key_exists('cobro', $datos)) {
            Ajuste::guardar('cobro', array_map(fn ($v) => trim((string) $v), array_filter($datos['cobro'] ?? [], fn ($v) => $v !== null)), $request->user()->id);
        }

        return $this->show($cobros);
    }

    public function enviarCuotasNuevas(Avisos $avisos): JsonResponse
    {
        return response()->json(['enviados' => $avisos->cuotasNuevas()]);
    }
}
