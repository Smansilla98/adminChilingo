<?php

namespace App\Jobs;

use App\Domain\Partituras\TranscriptorClient;
use App\Models\PartituraTranscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Envía la grabación al servicio transcriptor. El resultado vuelve por el aviso
 * firmado (TranscriptorCallbackController), no por este job.
 */
class TranscribirAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $transcripcionId) {}

    public function handle(TranscriptorClient $cliente): void
    {
        $t = PartituraTranscripcion::query()->find($this->transcripcionId);
        if (! $t || $t->terminada()) {
            return;
        }
        $cliente->enviar($t);
        $t->update(['estado' => PartituraTranscripcion::PROCESANDO, 'progreso' => max(5, $t->progreso)]);
    }

    public function failed(Throwable $e): void
    {
        PartituraTranscripcion::query()->whereKey($this->transcripcionId)->update([
            'estado' => PartituraTranscripcion::ERROR,
            'error' => mb_substr('No se pudo contactar al servicio de transcripción. '.$e->getMessage(), 0, 500),
        ]);
    }
}
