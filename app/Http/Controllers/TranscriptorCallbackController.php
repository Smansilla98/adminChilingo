<?php

namespace App\Http\Controllers;

use App\Domain\Partituras\Transcripciones;
use App\Domain\Partituras\TranscriptorClient;
use App\Models\PartituraTranscripcion;
use App\Support\ArchivoSeguro;
use App\Support\PartituraAudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rutas internas del servicio transcriptor: bajar la grabación (enlace firmado y
 * temporal) y avisar el resultado (cuerpo firmado con HMAC-SHA256).
 */
class TranscriptorCallbackController extends Controller
{
    public function aviso(Request $request, Transcripciones $servicio): JsonResponse
    {
        $cuerpo = (string) $request->getContent();
        if (! TranscriptorClient::firmaValida($cuerpo, $request->header(TranscriptorClient::CABECERA_FIRMA))) {
            return response()->json(['ok' => false, 'message' => 'Firma inválida.'], 401);
        }
        $datos = json_decode($cuerpo, true);
        if (! is_array($datos) || empty($datos['job_id'])) {
            return response()->json(['ok' => false, 'message' => 'Aviso sin job_id.'], 422);
        }
        $t = $servicio->recibirAviso($datos);
        if (! $t) {
            return response()->json(['ok' => false, 'message' => 'No existe ese pedido.'], 404);
        }

        return response()->json(['ok' => true, 'estado' => $t->estado]);
    }

    public function audio(PartituraTranscripcion $transcripcion): StreamedResponse
    {
        abort_if($transcripcion->terminada(), 410, 'El pedido ya terminó.');

        return ArchivoSeguro::responder(PartituraAudio::disco(), $transcripcion->audio_path, basename($transcripcion->audio_path), false);
    }
}
