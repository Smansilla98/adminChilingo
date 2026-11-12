<?php

namespace App\Http\Controllers;

use App\Domain\Partituras\Transcripciones;
use App\Domain\Partituras\TranscriptorClient;
use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Services\PartituraExportador;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Editor web: grabación de referencia, exportación del servidor y transcripción automática.
 */
class PartituraGrabacionController extends Controller
{
    /** Grabación de referencia (de la versión publicada, de un borrador o de una versión vieja). */
    public function audio(ProgramaRitmo $programaRitmo, string $archivo): StreamedResponse
    {
        $this->abortSiNoPublico($programaRitmo);

        return PartituraAudio::responder($programaRitmo, $archivo);
    }

    public function subirAudio(Request $request, ProgramaRitmo $programaRitmo): JsonResponse
    {
        $this->exigirEdicion($programaRitmo);
        $request->validate([
            'editor_nombre' => 'required|string|min:2|max:80',
            'audio' => PartituraAudio::reglas(),
        ], PartituraAudio::mensajes() + ['editor_nombre.required' => 'Indicá tu nombre para dejar registro.']);

        $ref = PartituraAudio::guardar($request->file('audio'), $programaRitmo);

        return response()->json([
            'ok' => true,
            'audio_ref' => $ref,
            'url' => route('programa.toque.audio', [$programaRitmo, basename((string) $ref['path'])]),
        ]);
    }

    /** MIDI o MusicXML generado en el servidor desde la partitura publicada. */
    public function exportar(ProgramaRitmo $programaRitmo, string $formato, PartituraExportador $exportador): Response
    {
        $this->abortSiNoPublico($programaRitmo);

        return self::respuestaExportada($programaRitmo, $formato, $exportador);
    }

    public static function respuestaExportada(ProgramaRitmo $toque, string $formato, PartituraExportador $exportador): Response
    {
        abort_unless(in_array($formato, PartituraExportador::FORMATOS, true), 404);
        $score = PartituraScore::normalizar($toque->mediosNormalizados()['partitura_score'] ?? null);
        abort_unless($score !== null, 404, 'Este toque no tiene partitura escrita.');
        $nombre = PartituraExportador::nombreArchivo($toque->nombre ?: $toque->slug, $formato);
        $contenido = $formato === 'midi' ? $exportador->midi($score) : $exportador->musicXml($score);
        $tipo = $formato === 'midi' ? 'audio/midi' : 'application/vnd.recordare.musicxml+xml';

        return response($contenido, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Pide la transcripción automática de la grabación de referencia (deja un borrador). */
    public function transcribir(Request $request, ProgramaRitmo $programaRitmo, Transcripciones $servicio, TranscriptorClient $cliente): JsonResponse
    {
        $this->exigirEdicion($programaRitmo);
        abort_unless(auth()->check(), 403, 'Para transcribir hay que iniciar sesión.');
        if (! $cliente->habilitado() || ! $servicio->disponible()) {
            return response()->json(['ok' => false, 'message' => $cliente->motivoApagado() ?? 'La transcripción no está disponible.'], 503);
        }
        $data = $request->validate(['path' => 'required|string|max:255', 'editor_nombre' => 'nullable|string|max:80']);
        $ruta = $servicio->audioDelToque($programaRitmo, (string) $data['path']);
        if ($ruta === null) {
            return response()->json(['ok' => false, 'message' => 'No encontramos esa grabación. Subila de nuevo.'], 422);
        }
        $t = $servicio->crear($programaRitmo, $ruta, $request->user(), $data['editor_nombre'] ?? $request->user()?->name);

        return response()->json(['ok' => true, 'data' => $servicio->presentar($t, $this->urlEstado($programaRitmo, $t))], 202);
    }

    public function transcripcion(ProgramaRitmo $programaRitmo, PartituraTranscripcion $transcripcion, Transcripciones $servicio): JsonResponse
    {
        $this->exigirEdicion($programaRitmo);
        abort_unless((int) $transcripcion->programa_ritmo_id === (int) $programaRitmo->id, 404);

        return response()->json(['data' => $servicio->presentar($transcripcion, $this->urlEstado($programaRitmo, $transcripcion))]);
    }

    private function urlEstado(ProgramaRitmo $toque, PartituraTranscripcion $t): string
    {
        return route('programa.toque.editor.transcripcion', [$toque, $t]);
    }

    private function exigirEdicion(ProgramaRitmo $programaRitmo): void
    {
        if (! config('chilinga.edicion_publica_programa') && ! auth()->user()?->can('partituras.admin')) {
            abort(403, 'La edición del programa requiere iniciar sesión con permiso.');
        }
        $this->abortSiNoPublico($programaRitmo);
    }

    private function abortSiNoPublico(ProgramaRitmo $programaRitmo): void
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
    }
}
