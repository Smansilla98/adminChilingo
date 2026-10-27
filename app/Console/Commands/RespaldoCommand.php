<?php

namespace App\Console\Commands;

use App\Domain\Datos\Respaldo;
use Illuminate\Console\Command;
use Throwable;

class RespaldoCommand extends Command
{
    protected $signature = 'chilinga:respaldo {--conservar=14 : Cuántos backups guardar}';

    protected $description = 'Backup de la base en SQL comprimido dentro del disco persistente (respaldos/).';

    public function handle(Respaldo $respaldo): int
    {
        try {
            $r = $respaldo->generar(max(1, (int) $this->option('conservar')));
        } catch (Throwable $e) {
            $this->error('El backup falló: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backup listo: %s (%s KB, %d tablas, %d filas, %ss)',
            $r['archivo'],
            number_format($r['bytes'] / 1024, 0, ',', '.'),
            $r['tablas'],
            $r['filas'],
            $r['segundos'],
        ));

        return self::SUCCESS;
    }
}
