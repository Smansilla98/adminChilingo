<?php

namespace App\Console\Commands;

use App\Domain\Finanzas\CobroCuotasService;
use Illuminate\Console\Command;

class MercadoPagoConciliarCommand extends Command
{
    protected $signature = 'chilinga:mercadopago:conciliar {--dias=7 : Hasta cuántos días atrás revisar}';

    protected $description = 'Registra pagos aprobados en Mercado Pago cuyo aviso no llegó (siguen «esperando pago»).';

    public function handle(CobroCuotasService $cobros): int
    {
        $r = $cobros->conciliarMercadoPago(max(1, (int) $this->option('dias')));
        $this->info("Revisados: {$r['revisados']} · registrados: {$r['registrados']}");

        return self::SUCCESS;
    }
}
