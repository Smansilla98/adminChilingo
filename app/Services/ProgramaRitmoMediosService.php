<?php

namespace App\Services;

use App\Models\ProgramaRitmo;
use App\Support\ProgramaRitmoMedios;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProgramaRitmoMediosService
{
    /**
     * @return array<string, mixed>
     */
    public function procesarDesdeRequest(Request $request, ProgramaRitmo $ritmo): array
    {
        $medios = ProgramaRitmoMedios::normalizar($ritmo->medios);
        $baseDir = 'programa-ritmos/'.$ritmo->id;

        $pathsAntes = $this->pathsEnMedios($medios);

        if ($request->boolean('quitar_partitura_vexflow')) {
            $medios['partitura_vexflow'] = null;
        } else {
            $json = $request->input('partitura_vexflow_json');
            if (is_string($json) && trim($json) !== '') {
                $decoded = json_decode($json, true);
                $medios['partitura_vexflow'] = ProgramaRitmoMedios::normalizarPartituraVexflow($decoded);
            }
        }

        if ($request->boolean('quitar_partitura')) {
            $medios['partitura'] = null;
        } elseif ($request->hasFile('partitura_archivo')) {
            $file = $request->file('partitura_archivo');
            $medios['partitura'] = [
                'path' => $this->guardarArchivo($file, $baseDir.'/partitura'),
                'nombre' => $file->getClientOriginalName(),
            ];
        }

        foreach (array_keys(ProgramaRitmoMedios::VIDEOS_BASE) as $key) {
            $medios['videos_base'][$key]['url'] = ProgramaRitmoMedios::limpiarUrl(
                $request->input('videos_base.'.$key.'.url')
            );
        }

        foreach (array_keys(ProgramaRitmoMedios::VIDEOS_GRUPO) as $key) {
            $medios['videos_grupo'][$key]['url'] = ProgramaRitmoMedios::limpiarUrl(
                $request->input('videos_grupo.'.$key.'.url')
            );
        }

        $medios['cortes'] = $this->procesarFilasConArchivo(
            $request->input('cortes', []),
            $request->file('cortes') ?? [],
            $medios['cortes'],
            $baseDir.'/cortes'
        );

        $medios['recursos'] = $this->procesarRecursos(
            $request->input('recursos', []),
            $request->file('recursos') ?? [],
            $medios['recursos'],
            $baseDir.'/recursos'
        );

        $pathsDespues = $this->pathsEnMedios($medios);
        foreach (array_diff($pathsAntes, $pathsDespues) as $orphan) {
            $this->borrarArchivo($orphan);
        }

        return $medios;
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $filesRows
     * @param  array<int, array<string, mixed>>  $previos
     * @return array<int, array<string, mixed>>
     */
    private function procesarFilasConArchivo(array $input, array $filesRows, array $previos, string $dir): array
    {
        $out = [];
        foreach ($input as $i => $row) {
            $titulo = trim((string) ($row['titulo'] ?? ''));
            $url = ProgramaRitmoMedios::limpiarUrl($row['url'] ?? null);
            $path = ! empty($row['path']) ? (string) $row['path'] : ($previos[$i]['path'] ?? null);
            $nombre = ! empty($row['nombre']) ? (string) $row['nombre'] : ($previos[$i]['nombre'] ?? null);

            if (! empty($row['quitar_archivo'])) {
                $path = null;
                $nombre = null;
            }

            $file = $filesRows[$i]['archivo'] ?? null;
            if ($file instanceof UploadedFile) {
                $path = $this->guardarArchivo($file, $dir.'/'.Str::uuid());
                $nombre = $file->getClientOriginalName();
            }

            if ($titulo !== '' || $url || $path) {
                $out[] = compact('titulo', 'url', 'path', 'nombre');
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $filesRows
     * @param  array<int, array<string, mixed>>  $previos
     * @return array<int, array<string, mixed>>
     */
    private function procesarRecursos(array $input, array $filesRows, array $previos, string $dir): array
    {
        $out = [];
        foreach ($input as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $tipo = (string) ($row['tipo'] ?? 'enlace');
            if (! array_key_exists($tipo, ProgramaRitmoMedios::TIPOS_RECURSO)) {
                $tipo = 'enlace';
            }
            $titulo = trim((string) ($row['titulo'] ?? ''));
            $url = ProgramaRitmoMedios::limpiarUrl($row['url'] ?? null);
            $contenido = trim((string) ($row['contenido'] ?? ''));
            $path = ! empty($row['path']) ? (string) $row['path'] : ($previos[$i]['path'] ?? null);
            $nombre = ! empty($row['nombre']) ? (string) $row['nombre'] : ($previos[$i]['nombre'] ?? null);

            if (! empty($row['quitar_archivo'])) {
                $path = null;
                $nombre = null;
            }

            $file = $filesRows[$i]['archivo'] ?? null;
            if ($file instanceof UploadedFile) {
                $path = $this->guardarArchivo($file, $dir.'/'.Str::uuid());
                $nombre = $file->getClientOriginalName();
            }

            if ($titulo !== '' || $url || $path || $contenido !== '') {
                $out[] = compact('tipo', 'titulo', 'url', 'contenido', 'path', 'nombre');
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $medios
     * @return array<int, string>
     */
    private function pathsEnMedios(array $medios): array
    {
        $paths = [];
        if (! empty($medios['partitura']['path'])) {
            $paths[] = $medios['partitura']['path'];
        }
        foreach (['cortes', 'recursos'] as $grupo) {
            foreach ($medios[$grupo] ?? [] as $item) {
                if (! empty($item['path'])) {
                    $paths[] = $item['path'];
                }
            }
        }

        return $paths;
    }

    private function guardarArchivo(UploadedFile $file, string $dir): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '') {
            $ext = strtolower((string) ($file->guessExtension() ?: 'bin'));
        }
        $filename = (string) Str::uuid().'.'.$ext;
        $file->storeAs(trim($dir, '/'), $filename, 'comprobantes');

        return trim($dir, '/').'/'.$filename;
    }

    private function borrarArchivo(?string $path): void
    {
        if ($path && Storage::disk('comprobantes')->exists($path)) {
            Storage::disk('comprobantes')->delete($path);
        }
    }
}
