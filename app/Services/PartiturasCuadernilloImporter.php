<?php

namespace App\Services;

use App\Models\ProgramaRitmo;
use App\Support\ProgramaRitmoMedios;
use App\Support\ProgramaRitmoSlug;
use Illuminate\Support\Facades\Schema;

/**
 * Importa las partituras del Cuadernillo (JSON en database/data/partituras)
 * hacia programa_ritmos.medios.partitura_vexflow.
 */
class PartiturasCuadernilloImporter
{
    /**
     * @return array{ok: int, fail: int, created: int, messages: list<string>}
     */
    public function importar(): array
    {
        $dir = database_path('data/partituras');
        $manifestPath = $dir.'/manifest.json';
        $messages = [];

        if (! is_file($manifestPath)) {
            return [
                'ok' => 0,
                'fail' => 1,
                'created' => 0,
                'messages' => ['No se encontró database/data/partituras/manifest.json'],
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return [
                'ok' => 0,
                'fail' => 1,
                'created' => 0,
                'messages' => ['manifest.json inválido'],
            ];
        }

        if (! Schema::hasTable('programa_ritmos') || ! Schema::hasColumn('programa_ritmos', 'medios')) {
            return [
                'ok' => 0,
                'fail' => 1,
                'created' => 0,
                'messages' => ['Falta la tabla/columna programa_ritmos.medios. Corré las migraciones.'],
            ];
        }

        $usaSlug = Schema::hasColumn('programa_ritmos', 'slug');
        $usaPublicado = Schema::hasColumn('programa_ritmos', 'publicado');
        $ok = 0;
        $fail = 0;
        $created = 0;

        foreach ($manifest as $item) {
            $file = $item['file'] ?? null;
            $pdfNombre = $item['pdf_nombre'] ?? $file;

            if (! $file || ! is_file($dir.'/'.$file)) {
                $messages[] = "Falta archivo: {$file}";
                $fail++;

                continue;
            }

            $raw = json_decode((string) file_get_contents($dir.'/'.$file), true);
            $partitura = ProgramaRitmoMedios::normalizarPartituraVexflow($raw);
            if ($partitura === null) {
                $messages[] = "Partitura inválida / sin golpes: {$file}";
                $fail++;

                continue;
            }

            $ritmo = null;
            if (($item['accion'] ?? '') === 'nuevo' && empty($item['match'])) {
                $crear = $item['crear'] ?? [];
                $ritmo = ProgramaRitmo::query()
                    ->where('año', $crear['año'] ?? 0)
                    ->where('nombre', $crear['nombre'] ?? '')
                    ->first();

                if (! $ritmo) {
                    $attrs = [
                        'año' => (int) ($crear['año'] ?? 1),
                        'orden' => (int) ($crear['orden'] ?? 99),
                        'nombre' => (string) ($crear['nombre'] ?? $pdfNombre),
                        'autor' => $crear['autor'] ?? null,
                        'opcional' => (bool) ($crear['opcional'] ?? false),
                        'notas' => $crear['notas'] ?? null,
                    ];
                    if ($usaPublicado) {
                        $attrs['publicado'] = true;
                    }
                    if ($usaSlug) {
                        $attrs['slug'] = ProgramaRitmoSlug::generar($attrs['año'], $attrs['nombre']);
                    }
                    $ritmo = ProgramaRitmo::create($attrs);
                    $created++;
                    $messages[] = "Creado: {$ritmo->nombre}";
                }
            } else {
                $m = $item['match'] ?? [];
                $ritmo = ProgramaRitmo::query()
                    ->where('año', $m['año'] ?? 0)
                    ->where('orden', $m['orden'] ?? 0)
                    ->where('nombre', $m['nombre'] ?? '')
                    ->first();
            }

            if (! $ritmo) {
                $messages[] = "Sin match: {$pdfNombre}";
                $fail++;

                continue;
            }

            $medios = ProgramaRitmoMedios::normalizar($ritmo->medios);
            $medios['partitura_vexflow'] = $partitura;
            $ritmo->medios = $medios;
            $ritmo->save();
            $ok++;
        }

        return compact('ok', 'fail', 'created', 'messages');
    }
}
