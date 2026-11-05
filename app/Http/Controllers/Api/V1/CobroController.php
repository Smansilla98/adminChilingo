<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\CobroCuotasService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Support\FichasAlumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** App del alumno: datos para pagar, cuotas que puede pagar y pago con Mercado Pago. */
class CobroController extends Controller
{
    public function show(Request $request, CobroCuotasService $cobros): JsonResponse
    {
        $fichas = FichasAlumno::delUsuario($request->user());

        return response()->json([
            'transferencia' => $cobros->datosTransferencia(),
            'mercado_pago' => $cobros->mercadoPagoDisponible(),
            'alumnos' => $fichas->map(fn (Alumno $a) => [
                'alumno_id' => $a->id,
                'nombre' => $a->nombre_apellido,
                'pendientes' => $cobros->pendientes($a)->map(fn (array $l) => [
                    'cuota_id' => $l['cuota_id'], 'cuota' => $l['cuota'], 'periodo' => $l['periodo'], 'vencida' => $l['vencida'],
                    'monto' => $l['monto'], 'bloque' => $l['bloque'], 'en_curso' => $l['en_curso'],
                ])->values(),
            ])->values(),
        ]);
    }

    /** Arma el pago de las cuotas elegidas (o de todas las pendientes) y devuelve el link de Mercado Pago. */
    public function mercadoPago(Request $request, CobroCuotasService $cobros): JsonResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer'],
            'cuota_ids' => ['nullable', 'array'],
            'cuota_ids.*' => ['integer'],
        ]);
        if (! $cobros->mercadoPagoDisponible()) {
            return response()->json(['message' => 'Mercado Pago no está disponible. Pagá por transferencia al alias.'], 422);
        }
        $alumno = FichasAlumno::delUsuario($request->user())->firstWhere('id', (int) $datos['alumno_id']);
        abort_unless($alumno instanceof Alumno, 403);

        $ids = $datos['cuota_ids'] ?? [];
        if ($ids === []) {
            $ids = $cobros->pendientes($alumno)->whereNull('en_curso')->pluck('cuota_id')->all();
        }
        if ($ids === []) {
            return response()->json(['message' => 'No tenés cuotas para pagar (o ya hay un pago en curso).'], 422);
        }

        $pago = $cobros->iniciar($request->user(), $alumno, $ids, 'mercadopago');
        try {
            $link = $cobros->linkMercadoPago($pago, $request->user()->email);
        } catch (RuntimeException $e) {
            $cobros->cancelar($pago);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['link' => $link, 'referencia' => $pago->referencia]);
    }
}
