<?php

namespace Database\Seeders;

use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Carga las partituras de ejemplo del editor v4 en medios.partitura_score.
 *
 * Fuente: database/data/partituras-v4/*.json + manifest.json
 * Uso: php artisan db:seed --class=PartiturasEjemploSeeder
 *      (sobreescribe siempre). En start.sh se llama en modo "incremental".
 *
 * Modo incremental (soloFaltantes = true): carga la partitura si falta Y TAMBIÉN
 * si lo guardado en la base salió de una versión vieja del JSON. Para eso cada
 * score guardado lleva el sello `fuente = {origen, hash}` con el hash del archivo
 * del repo: si el hash cambió, la base quedó vieja y se recarga.
 */
class PartiturasEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $soloFaltantes = filter_var(
            getenv('PARTITURAS_SEED_SOLO_FALTANTES') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );

        $this->cargar($soloFaltantes);
    }

    /**
     * @return array{ok: int, skip: int, fail: int}
     */
    public function cargar(bool $soloFaltantes = false): array
    {
        $vacio = ['ok' => 0, 'skip' => 0, 'fail' => 0];

        if (! Schema::hasColumn('programa_ritmos', 'medios')) {
            $this->command?->warn('La tabla programa_ritmos no tiene la columna medios. Corré las migraciones primero.');

            return $vacio;
        }

        $dir = database_path('data/partituras-v4');
        $manifestPath = $dir.'/manifest.json';

        if (! is_file($manifestPath)) {
            $this->command?->warn('No se encontró database/data/partituras-v4/manifest.json');

            return $vacio;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->command?->warn('manifest.json inválido.');

            return $vacio;
        }

        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach ($manifest as $item) {
            $file = $dir.'/'.($item['file'] ?? '');
            $titulo = (string) ($item['title'] ?? ($item['file'] ?? 'toque'));

            if (! is_file($file)) {
                $this->command?->line("· {$titulo}: falta el archivo {$file}");
                $fail++;

                continue;
            }

            $contenido = (string) file_get_contents($file);
            $hash = PartituraScore::hashDeFuente($contenido);
            $raw = json_decode($contenido, true);
            if (is_array($raw)) {
                $raw['fuente'] = ['origen' => 'partituras-v4/'.basename($file), 'hash' => $hash];
            }

            $score = PartituraScore::normalizar($raw);
            if ($score === null) {
                $this->command?->line("· {$titulo}: JSON inválido para el modelo v4");
                $fail++;

                continue;
            }

            $ritmo = $this->buscarRitmo($item['match'] ?? [], $titulo);
            if (! $ritmo) {
                $this->command?->line("· {$titulo}: no encontré el toque en programa_ritmos");
                $fail++;

                continue;
            }

            $medios = $ritmo->mediosNormalizados();
            $actual = $medios['partitura_score'] ?? null;
            $hashActual = is_array($actual) ? (string) ($actual['fuente']['hash'] ?? '') : '';

            if ($soloFaltantes && PartituraScore::tieneGolpes($actual) && $hashActual === $hash) {
                $this->command?->line("· {$ritmo->nombre}: ya está al día, se omite");
                $skip++;

                continue;
            }

            if ($soloFaltantes && PartituraScore::tieneGolpes($actual)) {
                $motivo = $hashActual === '' ? 'sin sello de fuente' : "sello viejo {$hashActual}";
                $this->command?->line("↻ {$ritmo->nombre}: {$motivo} → recargo desde el JSON ({$hash})");
            }

            $medios['partitura_score'] = $score;
            $ritmo->update(['medios' => $medios]);

            $resumen = PartituraScore::resumen($score);
            $this->command?->line("✓ {$ritmo->nombre}: {$resumen['compases']} compases, {$resumen['golpes']} golpes");
            $ok++;
        }

        $this->command?->info("Listo: {$ok} cargadas, {$skip} ya estaban, {$fail} omitidas.");

        return compact('ok', 'skip', 'fail');
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function buscarRitmo(array $match, string $titulo): ?ProgramaRitmo
    {
        $query = ProgramaRitmo::query();

        if (! empty($match['nombre'])) {
            $ritmo = (clone $query)->where('nombre', $match['nombre'])->first();
            if ($ritmo) {
                return $ritmo;
            }
        }

        if (isset($match['año'], $match['orden'])) {
            $ritmo = (clone $query)->where('año', (int) $match['año'])->where('orden', (int) $match['orden'])->first();
            if ($ritmo) {
                return $ritmo;
            }
        }

        return (clone $query)->where('nombre', 'like', '%'.$titulo.'%')->first();
    }
}
