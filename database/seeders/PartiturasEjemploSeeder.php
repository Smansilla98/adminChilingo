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
 *      (sobreescribe). En start.sh se llama con solo-faltantes.
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

            $score = PartituraScore::normalizar(json_decode((string) file_get_contents($file), true));
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
            if ($soloFaltantes && PartituraScore::tieneGolpes($medios['partitura_score'] ?? null)) {
                $this->command?->line("· {$ritmo->nombre}: ya tiene partitura, se omite");
                $skip++;

                continue;
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
