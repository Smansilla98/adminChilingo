<?php

namespace App\Console\Commands;

use App\Domain\Archivo\ImagenesArchivo;
use App\Models\ArchivoFoto;
use Illuminate\Console\Command;

/**
 * Genera los derivados web que falten (o todos con --todas). Útil tras cambiar los
 * anchos, migrar el disco o habilitar WebP/JPEG en GD. Nunca toca los originales.
 */
class ArchivoDerivadosCommand extends Command
{
    protected $signature = 'archivo:derivados {--todas : Regenera también las fotos que ya tienen derivados} {--id=* : Solo estas fotos}';

    protected $description = 'Genera los derivados WebP del archivo histórico a partir de los originales.';

    public function handle(ImagenesArchivo $imagenes): int
    {
        $query = ArchivoFoto::query()
            ->when(! $this->option('todas'), fn ($q) => $q->whereNull('derivados'))
            ->when($this->option('id'), fn ($q, $ids) => $q->whereIn('id', $ids));
        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay fotos para procesar.');

            return self::SUCCESS;
        }

        $sinDerivados = 0;
        $barra = $this->output->createProgressBar($total);
        $query->orderBy('id')->chunkById(50, function ($fotos) use ($imagenes, $barra, &$sinDerivados) {
            foreach ($fotos as $foto) {
                if (empty($imagenes->generarDerivados($foto)->derivados)) {
                    $sinDerivados++;
                }
                $barra->advance();
            }
        });
        $barra->finish();
        $this->newLine();
        $this->info("Procesadas: {$total}.");
        if ($sinDerivados) {
            $this->warn("{$sinDerivados} no se pudieron decodificar (¿GD sin JPEG/WebP o original faltante?).");
        }

        return $sinDerivados ? self::FAILURE : self::SUCCESS;
    }
}
