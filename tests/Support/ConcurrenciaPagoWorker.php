<?php

/**
 * Proceso hijo de la prueba de concurrencia real (ConcurrenciaPagoMysqlTest): registra un pago
 * como lo haría un usuario en la caja. Espera hasta la hora de arranque común para que los dos
 * procesos compitan de verdad, y escribe OK <id> o ERROR <clase> <mensaje> por la salida.
 *
 * Uso: php tests/Support/ConcurrenciaPagoWorker.php '<json>'
 */

use App\Domain\Finanzas\RegistroPagoService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$datos = json_decode((string) ($argv[1] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);

while (microtime(true) < (float) $datos['arranque']) {
    usleep(1000);
}

try {
    $pago = app(RegistroPagoService::class)->registrar($datos['validado'], User::query()->findOrFail((int) $datos['usuario_id']));
    echo 'OK '.$pago->id;
} catch (Throwable $e) {
    echo 'ERROR '.get_class($e).' '.str_replace(["\n", "\r"], ' ', $e->getMessage());
}
