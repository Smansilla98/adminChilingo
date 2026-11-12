<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Partituras\Transcripciones;
use App\Domain\Partituras\TranscriptorClient;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PartituraGrabacionController as Web;
use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Services\PartituraExportador;
use App\Support\AccesoMaterial;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * App: grabación de referencia, MIDI/MusicXML y transcripción automática.
 */
class PartituraGrabacionController extends Controller
{
    public function estado(Request $request, TranscriptorClient $cliente): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);

        return response()->json(['transcripcion' => ['habilitada' => $cliente->habilitado(), 'mensaje' => $cliente->motivoApagado()]]);
    }

    public function exportar(Request $request, string $slug, string $formato, PartituraExportador $exportador): Response
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);

        return Web::respuestaExportada($this->visible($request, $slug), $formato, $exportador);
    }

    public function audio(Request $request, string $slug, string $archivo): StreamedResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);

        return PartituraAudio::responder($this->visible($request, $slug), $archivo);
    }

    /** Sube la grabación (y, si se pide, la manda a transcribir). Devuelve el audio_ref. */
    public function subirAudio(Request $request, string $slug, Transcripciones $servicio, TranscriptorClient $cliente): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $request->validate(['audio' => PartituraAudio::reglas(), 'transcribir' => 'sometimes|boolean'], PartituraAudio::mensajes());
        $ref = PartituraAudio::guardar($request->file('audio'), $toque);
        // La grabación queda como parte de la partitura publicada (sin crear versión: la música no cambia).
        $medios = $toque->mediosNormalizados();
        if (is_array($medios['partitura_score'] ?? null)) {
            $medios['partitura_score']['audio_ref'] = $ref;
            $toque->update(['medios' => $medios]);
        }
        $out = ['ok' => true, 'audio_ref' => $ref, 'transcripcion' => null];
        if ($request->boolean('transcribir')) {
            if (! $cliente->habilitado() || ! $servicio->disponible()) {
                $out['aviso'] = $cliente->motivoApagado();
            } else {
                $t = $servicio->crear($toque, (string) $ref['path'], $request->user(), $request->user()->name);
                $out['transcripcion'] = $servicio->presentar($t, $this->urlEstado($toque, $t));
            }
        }

        return response()->json($out, 201);
    }

    /** Ajusta dónde cae el primer tiempo de la grabación (y sus pulsos). No crea versión: la música no cambia. */
    public function ajustarInicio(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'offset_seg' => 'required|numeric|min:0|max:600',
            'beats' => 'nullable|array|max:'.PartituraScore::AUDIO_MAX_BEATS,
            'beats.*' => 'numeric|min:0|max:7200',
        ]);
        $medios = $toque->mediosNormalizados();
        $actual = $medios['partitura_score']['audio_ref'] ?? null;
        abort_unless(is_array($actual) && ! empty($actual['path']), 422, 'Primero subí la grabación del toque.');
        $ref = PartituraScore::normalizarAudioRef(array_merge($actual, [
            'offset_seg' => $data['offset_seg'],
            'beats' => $data['beats'] ?? [],
        ]));
        $medios['partitura_score']['audio_ref'] = $ref;
        $toque->update(['medios' => $medios]);

        return response()->json(['ok' => true, 'audio_ref' => $ref]);
    }

    public function transcribir(Request $request, string $slug, Transcripciones $servicio, TranscriptorClient $cliente): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        if (! $cliente->habilitado() || ! $servicio->disponible()) {
            return response()->json(['message' => $cliente->motivoApagado() ?? 'La transcripción no está disponible.'], 503);
        }
        $data = $request->validate(['path' => 'required|string|max:255']);
        $ruta = $servicio->audioDelToque($toque, (string) $data['path']);
        abort_unless($ruta !== null, 422, 'No encontramos esa grabación. Subila de nuevo.');
        $t = $servicio->crear($toque, $ruta, $request->user(), $request->user()->name);

        return response()->json(['data' => $servicio->presentar($t, $this->urlEstado($toque, $t))], 202);
    }

    public function transcripcion(Request $request, string $slug, int $id, Transcripciones $servicio): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $t = PartituraTranscripcion::query()->where('programa_ritmo_id', $toque->id)->findOrFail($id);

        return response()->json(['data' => $servicio->presentar($t, $this->urlEstado($toque, $t))]);
    }

    private function urlEstado(ProgramaRitmo $toque, PartituraTranscripcion $t): string
    {
        return route('api.v1.partituras.transcripcion', [$toque->slug, $t->id]);
    }

    private function visible(Request $request, string $slug): ProgramaRitmo
    {
        $q = ProgramaRitmo::query()->where('slug', $slug);
        if (! $request->user()->acceso()->puede('partituras.admin') && Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where('publicado', true);
        }
        $toque = $q->firstOrFail();
        abort_unless(AccesoMaterial::puedeVer($request->user(), $toque), 403, 'Este material es de '.(int) $toque->año.'° año: se abre cuando tu bloque llegue a ese año.');

        return $toque;
    }
}
