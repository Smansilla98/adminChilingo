<?php

namespace App\Console\Commands;

use App\Domain\Partituras\DatasetCorrecciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Exporta las correcciones humanas de las transcripciones a JSONL (para medir y entrenar). */
class PartiturasDatasetCorreccionesCommand extends Command
{
    protected $signature = 'partituras:dataset-correcciones {salida? : Ruta del archivo .jsonl (por defecto storage/app/partituras-dataset.jsonl)}';

    protected $description = 'Exporta borrador automático vs versión publicada de cada transcripción corregida (JSONL)';

    public function handle(DatasetCorrecciones $dataset): int
    {
        $ruta = (string) ($this->argument('salida') ?: storage_path('app/partituras-dataset.jsonl'));
        File::ensureDirectoryExists(dirname($ruta));
        $fh = fopen($ruta, 'w');
        if ($fh === false) {
            $this->error('No se pudo escribir '.$ruta);

            return self::FAILURE;
        }
        $n = 0;
        foreach ($dataset->registros() as $registro) {
            fwrite($fh, json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $n++;
        }
        fclose($fh);
        $this->info("Dataset: {$n} toques corregidos en {$ruta}");

        return self::SUCCESS;
    }
}
