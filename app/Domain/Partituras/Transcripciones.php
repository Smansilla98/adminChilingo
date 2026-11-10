<?php

namespace App\Domain\Partituras;

use App\Jobs\TranscribirAudio;
use App\Models\Auditoria;
use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Models\User;
use App\Services\PartituraHistorialService;
use App\Support\PartituraAudio;
use App\Support\PartituraDesdeGolpes;
use App\Support\PartituraScore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transcripción automática de una grabación a un BORRADOR de partitura.
 * Nunca publica: alguien del equipo revisa el borrador en el editor y lo publica.
 */
class Transcripciones
{
    public function __construct(
        private readonly PartituraHistorialService $historial,
    ) {}

    public function disponible(): bool
    {
        return Schema::hasTable('partitura_transcripciones');
    }

    public function crear(ProgramaRitmo $toque, string $audioPath, ?User $user, ?string $autor): PartituraTranscripcion
    {
        $t = PartituraTranscripcion::query()->create([
            'programa_ritmo_id' => $toque->id,
            'user_id' => $user?->id,
            'estado' => PartituraTranscripcion::PENDIENTE,
            'progreso' => 0,
            'audio_path' => $audioPath,
            'autor' => $autor ? mb_substr($autor, 0, 80) : null,
        ]);
        try {
            Auditoria::registrar('partitura.transcripcion', $toque, null, ['transcripcion_id' => $t->id]);
        } catch (Throwable) {
            // La auditoría no frena el pedido.
        }
        try {
            TranscribirAudio::dispatch($t->id);
        } catch (Throwable $e) {
            // Con la cola sync el error llega acá; el job ya dejó el pedido en «error».
            $t->refresh();
            if (! $t->terminada()) {
                $t->update(['estado' => PartituraTranscripcion::ERROR, 'error' => 'No se pudo contactar al servicio de transcripción.']);
            }
        }

        return $t->refresh();
    }

    /**
     * Aviso del servicio (ya verificada la firma).
     *
     * @param  array<string, mixed>  $datos  {job_id, estado, progreso?, resultado?, error?}
     */
    public function recibirAviso(array $datos): ?PartituraTranscripcion
    {
        $t = PartituraTranscripcion::query()->find((int) ($datos['job_id'] ?? 0));
        if (! $t || $t->terminada()) {
            return $t;
        }
        $estado = (string) ($datos['estado'] ?? '');
        if ($estado === 'error') {
            $t->update(['estado' => PartituraTranscripcion::ERROR, 'error' => mb_substr((string) ($datos['error'] ?? 'El servicio no pudo procesar la grabación.'), 0, 500)]);

            return $t;
        }
        if ($estado !== 'listo') {
            $t->update(['estado' => PartituraTranscripcion::PROCESANDO, 'progreso' => max($t->progreso, min(99, (int) ($datos['progreso'] ?? 0)))]);

            return $t;
        }

        $resultado = is_array($datos['resultado'] ?? null) ? $datos['resultado'] : [];
        $toque = $t->programaRitmo;
        $audioRef = PartituraScore::normalizarAudioRef(['path' => $t->audio_path, 'nombre' => 'Grabación transcripta']);
        $conv = PartituraDesdeGolpes::convertir(
            $resultado,
            (string) ($toque->nombre ?? 'Toque'),
            $audioRef,
            (float) config('transcriptor.confianza_minima', 0.5),
        );
        if ($conv['score'] === null) {
            $t->update(['estado' => PartituraTranscripcion::ERROR, 'resultado' => $resultado, 'error' => 'No se reconocieron golpes en la grabación. Probá con un audio más limpio o más cercano al grupo.']);

            return $t;
        }
        $this->historial->guardarBorrador($toque, $conv['score'], PartituraDesdeGolpes::AUTOR);
        $t->update([
            'estado' => PartituraTranscripcion::LISTO,
            'progreso' => 100,
            'resultado' => array_merge($resultado, ['resumen' => [
                'golpes' => $conv['golpes'], 'compases' => $conv['compases'], 'dudosos' => $conv['dudosos'], 'truncado' => $conv['truncado'],
            ]]),
            'borrador' => $conv['score'],
            'error' => null,
        ]);

        return $t;
    }

    /** @return array<string, mixed> */
    public function presentar(PartituraTranscripcion $t, string $url): array
    {
        $resumen = $t->resultado['resumen'] ?? null;

        return [
            'id' => $t->id,
            'estado' => $t->estado,
            'progreso' => $t->progreso,
            'error' => $t->error,
            'resumen' => $resumen,
            'creada' => $t->created_at?->toIso8601String(),
            'url' => $url,
        ];
    }

    /** ¿El archivo pedido es una grabación de este toque que existe en el disco? */
    public function audioDelToque(ProgramaRitmo $toque, string $path): ?string
    {
        $archivo = basename($path);
        if ($path !== PartituraScore::AUDIO_CARPETA.'/'.$archivo) {
            return null;
        }

        return PartituraAudio::rutaDe($toque, $archivo);
    }

    public function existeAudio(string $path): bool
    {
        return Storage::disk(PartituraAudio::disco())->exists($path);
    }
}
