<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\EdicionPrograma;
use App\Domain\Partituras\VideosToque;
use App\Http\Controllers\Controller;
use App\Models\ProgramaRitmo;
use App\Support\AccesoMaterial;
use App\Support\ArchivoSeguro;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use App\Support\VideosMultipista;
use App\Support\VideoStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** Videos multipista para la app: lista (en la ficha), entrega del archivo y edición. */
class PartituraVideosController extends Controller
{
    public function ver(Request $request, string $slug, string $archivo): Response
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $toque = $this->visible($request, $slug);
        $video = collect(VideosMultipista::normalizar($toque->mediosNormalizados()['videos_multipista'] ?? []))
            ->first(fn (array $v) => basename($v['archivo']) === $archivo);
        abort_unless($video !== null, 404, 'No está ese video.');

        return VideoStream::responder($request, PartituraAudio::disco(), $video['archivo'], $archivo);
    }

    public function store(Request $request, string $slug, VideosToque $videos): JsonResponse
    {
        $this->exigirEdicion($request);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'extensions:'.implode(',', VideosMultipista::EXTENSIONES), 'max:'.VideosMultipista::MAX_KB, ArchivoSeguro::regla()],
            'instrumento' => 'required|string|max:40',
            'titulo' => 'nullable|string|max:80',
            'offset_seg' => 'nullable|numeric|min:-10|max:10',
        ], [
            'archivo.required' => 'Elegí un video (MP4, WebM o MOV).',
            'archivo.max' => 'El video no puede pasar de 100 MB.',
        ]);
        try {
            $item = $videos->agregar($toque, $request->file('archivo'), $this->instrumentoValido($datos['instrumento']), $datos['titulo'] ?? null, (float) ($datos['offset_seg'] ?? 0));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'video' => $this->presentar($toque, $item)], 201);
    }

    public function update(Request $request, string $slug, string $id, VideosToque $videos): JsonResponse
    {
        $this->exigirEdicion($request);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $datos = $request->validate([
            'titulo' => 'sometimes|nullable|string|max:80',
            'instrumento' => 'sometimes|string|max:40',
            'offset_seg' => 'sometimes|numeric|min:-10|max:10',
        ]);
        $cambios = [];
        if (array_key_exists('titulo', $datos)) {
            $cambios['titulo'] = (string) $datos['titulo'];
        }
        if (array_key_exists('instrumento', $datos)) {
            $cambios['instrumento'] = $this->instrumentoValido((string) $datos['instrumento']);
        }
        if (array_key_exists('offset_seg', $datos)) {
            $cambios['offset_seg'] = (float) $datos['offset_seg'];
        }
        $item = $videos->actualizar($toque, $id, $cambios);
        abort_unless($item !== null, 404, 'No está ese video.');

        return response()->json(['ok' => true, 'video' => $this->presentar($toque, $item)]);
    }

    public function mover(Request $request, string $slug, string $id, VideosToque $videos): JsonResponse
    {
        $this->exigirEdicion($request);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $direccion = $request->input('direccion') === 'arriba' ? -1 : 1;

        return response()->json(['ok' => $videos->mover($toque, $id, $direccion), 'videos' => $this->listado($toque)]);
    }

    public function destroy(Request $request, string $slug, string $id, VideosToque $videos): JsonResponse
    {
        $this->exigirEdicion($request);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        abort_unless($videos->quitar($toque, $id), 404, 'No está ese video.');

        return response()->json(['ok' => true, 'videos' => $this->listado($toque)]);
    }

    /**
     * Lista para la app (en la ficha del toque): id, instrumento, título, offset, orden y la URL
     * de la entrega autenticada.
     *
     * @return list<array<string, mixed>>
     */
    public function listado(ProgramaRitmo $toque): array
    {
        return collect(VideosMultipista::normalizar($toque->mediosNormalizados()['videos_multipista'] ?? []))
            ->map(fn (array $v) => $this->presentar($toque, $v))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $v */
    public function presentar(ProgramaRitmo $toque, array $v): array
    {
        return [
            'id' => $v['id'],
            'instrumento' => $v['instrumento'],
            'titulo' => $v['titulo'],
            'offset_seg' => $v['offset_seg'],
            'orden' => $v['orden'],
            'url' => route('api.v1.partituras.video', [$toque->slug, basename($v['archivo'])]),
        ];
    }

    private function instrumentoValido(string $id): string
    {
        return $id === VideosMultipista::INSTRUMENTO_VOZ || (array_key_exists($id, PartituraScore::INSTRUMENTOS) && $id !== 'todos')
            ? $id
            : VideosMultipista::INSTRUMENTO_VOZ;
    }

    private function exigirEdicion(Request $request): void
    {
        abort_unless(EdicionPrograma::puedeEditar($request->user()), 403, 'Para editar los videos hace falta el permiso de edición de partituras.');
    }

    /** Mismo criterio que el resto de la API de partituras: solo publicados (salvo admin) y dentro del año. */
    private function visible(Request $request, string $slug): ProgramaRitmo
    {
        $q = ProgramaRitmo::query()->where('slug', $slug);
        $admin = $request->user()->acceso()->puede('partituras.admin');
        if (! $admin && Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where('publicado', true);
        }
        $toque = $q->firstOrFail();
        abort_unless(AccesoMaterial::puedeVer($request->user(), $toque), 403, 'Este material es de '.(int) $toque->año.'° año: se abre cuando tu bloque llegue a ese año.');

        return $toque;
    }
}
