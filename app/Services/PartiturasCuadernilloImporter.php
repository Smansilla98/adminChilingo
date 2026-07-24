<?php

namespace App\Services;

use App\Models\ProgramaRitmo;
use App\Support\ProgramaRitmoMedios;
use App\Support\ProgramaRitmoSlug;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Asigna a cada toque el PDF del Cuadernillo (páginas correspondientes)
 * en medios.partitura, para visualización en el front.
 *
 * Fuente: database/data/partituras-pdf/*.pdf + manifest.json
 */
class PartiturasCuadernilloImporter
{
    /**
     * @return array{ok: int, fail: int, created: int, messages: list<string>}
     */
    public function importar(): array
    {
        $manifestPath = database_path('data/partituras/manifest.json');
        $pdfDir = database_path('data/partituras-pdf');
        $messages = [];

        if (! is_file($manifestPath)) {
            return $this->fail('No se encontró database/data/partituras/manifest.json');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return $this->fail('manifest.json inválido');
        }

        if (! is_dir($pdfDir)) {
            return $this->fail('No está la carpeta database/data/partituras-pdf (PDFs del cuadernillo).');
        }

        if (! Schema::hasTable('programa_ritmos') || ! Schema::hasColumn('programa_ritmos', 'medios')) {
            return $this->fail('Falta la tabla/columna programa_ritmos.medios. Corré las migraciones.');
        }

        $usaSlug = Schema::hasColumn('programa_ritmos', 'slug');
        $usaPublicado = Schema::hasColumn('programa_ritmos', 'publicado');
        $disk = Storage::disk('comprobantes');
        $ok = 0;
        $fail = 0;
        $created = 0;

        foreach ($manifest as $item) {
            $pdfNombre = $item['pdf_nombre'] ?? ($item['file'] ?? 'toque');
            $pdfFile = $item['pdf_file'] ?? null;
            $source = $pdfFile ? $pdfDir.'/'.$pdfFile : null;

            if (! $source || ! is_file($source)) {
                $messages[] = "Falta PDF: {$pdfFile}";
                $fail++;

                continue;
            }

            $ritmo = $this->resolverRitmo($item, $pdfNombre, $usaSlug, $usaPublicado, $created, $messages);
            if (! $ritmo) {
                $messages[] = "Sin match: {$pdfNombre}";
                $fail++;

                continue;
            }

            $medios = ProgramaRitmoMedios::normalizar($ritmo->medios);
            $dir = 'programa/'.$ritmo->id.'/partitura';
            $filename = Str::slug($pdfNombre).'-cuadernillo.pdf';
            $dest = $dir.'/'.$filename;

            // Reemplaza partitura anterior del toque
            if (! empty($medios['partitura']['path']) && $medios['partitura']['path'] !== $dest) {
                if ($disk->exists($medios['partitura']['path'])) {
                    $disk->delete($medios['partitura']['path']);
                }
            }

            $disk->put($dest, (string) file_get_contents($source));

            $pages = $item['pdf_pages'] ?? null;
            $pageLabel = is_array($pages) && count($pages) === 2
                ? ( $pages[0] === $pages[1] ? 'p.'.$this->cuadernilloPage($pages[0]) : 'p.'.$this->cuadernilloPage($pages[0]).'–'.$this->cuadernilloPage($pages[1]) )
                : null;

            $medios['partitura'] = [
                'path' => $dest,
                'nombre' => $pdfNombre.($pageLabel ? " ({$pageLabel})" : '').'.pdf',
            ];
            $ritmo->medios = $medios;
            $ritmo->save();
            $ok++;
        }

        return compact('ok', 'fail', 'created', 'messages');
    }

    /**
     * PDF page → número impreso del cuadernillo (portada+objetivo+índice = 3).
     */
    private function cuadernilloPage(int $pdfPage): int
    {
        return max(1, $pdfPage - 3);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $messages
     */
    private function resolverRitmo(array $item, string $pdfNombre, bool $usaSlug, bool $usaPublicado, int &$created, array &$messages): ?ProgramaRitmo
    {
        if (($item['accion'] ?? '') === 'nuevo' && empty($item['match'])) {
            $crear = $item['crear'] ?? [];
            $ritmo = ProgramaRitmo::query()
                ->where('año', $crear['año'] ?? 0)
                ->where('nombre', $crear['nombre'] ?? '')
                ->first();

            if ($ritmo) {
                return $ritmo;
            }

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

            return $ritmo;
        }

        $m = $item['match'] ?? [];

        return ProgramaRitmo::query()
            ->where('año', $m['año'] ?? 0)
            ->where('orden', $m['orden'] ?? 0)
            ->where('nombre', $m['nombre'] ?? '')
            ->first();
    }

    /**
     * @return array{ok: int, fail: int, created: int, messages: list<string>}
     */
    private function fail(string $message): array
    {
        return [
            'ok' => 0,
            'fail' => 1,
            'created' => 0,
            'messages' => [$message],
        ];
    }
}
