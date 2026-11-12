<?php

namespace App\Domain\Partituras;

use App\Models\PartituraTranscripcion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Cliente del servicio transcriptor (transcriptor/ en el repo, FastAPI).
 *
 * Le pasa al servicio un enlace firmado y temporal para bajar la grabación (el disco
 * de archivos es privado) y la URL donde avisar el resultado. Los dos sentidos van
 * firmados con HMAC-SHA256 y el secreto compartido TRANSCRIPTOR_SECRETO.
 */
class TranscriptorClient
{
    public const CABECERA_FIRMA = 'X-Transcriptor-Firma';

    public function habilitado(): bool
    {
        return filled(config('transcriptor.url')) && filled(config('transcriptor.secreto'));
    }

    /** Texto para la interfaz cuando la función está apagada. */
    public function motivoApagado(): ?string
    {
        if ($this->habilitado()) {
            return null;
        }

        return 'La transcripción automática todavía no está activada en este sistema. Podés escribir la partitura a mano o pedirle a quien administra que la active.';
    }

    public static function firmar(string $cuerpo): string
    {
        return 'sha256='.hash_hmac('sha256', $cuerpo, (string) config('transcriptor.secreto'));
    }

    public static function firmaValida(string $cuerpo, ?string $firma): bool
    {
        $secreto = (string) config('transcriptor.secreto');
        if ($secreto === '' || ! is_string($firma) || $firma === '') {
            return false;
        }

        return hash_equals(self::firmar($cuerpo), $firma);
    }

    /** Pide la transcripción. El servicio responde 202 y procesa en segundo plano. */
    public function enviar(PartituraTranscripcion $t): void
    {
        if (! $this->habilitado()) {
            throw new RuntimeException('El servicio de transcripción no está configurado.');
        }
        $payload = [
            'job_id' => (string) $t->id,
            'audio_url' => URL::temporarySignedRoute('transcriptor.audio', now()->addHours(max(1, (int) config('transcriptor.horas_enlace', 3))), ['transcripcion' => $t->id]),
            'callback_url' => route('transcriptor.callback'),
            'separar_fuentes' => (bool) config('transcriptor.separar_fuentes', false),
        ];
        $cuerpo = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $resp = Http::timeout((int) config('transcriptor.timeout', 20))
            ->withHeaders([self::CABECERA_FIRMA => self::firmar($cuerpo), 'Content-Type' => 'application/json', 'Accept' => 'application/json'])
            ->withBody($cuerpo, 'application/json')
            ->post(rtrim((string) config('transcriptor.url'), '/').'/transcribir');
        if (! $resp->successful()) {
            throw new RuntimeException('El servicio de transcripción respondió '.$resp->status().'.');
        }
    }
}
