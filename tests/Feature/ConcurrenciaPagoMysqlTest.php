<?php

namespace Tests\Feature;

use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Concurrencia real (MySQL, dos procesos): dos cajas registran a la vez el mismo pago (mismo
 * alumno, misma cuota). Solo uno puede quedar registrado.
 *
 * No corre con RefreshDatabase (cada prueba va en una transacción que los otros procesos no ven),
 * así que necesita una base MySQL dedicada, que se migra y se llena con datos confirmados:
 *
 *   CONCURRENCIA_DB=chilinga_concurrencia DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
 *   DB_USERNAME=root DB_PASSWORD=password php artisan test --filter=ConcurrenciaPagoMysql
 *
 * Sin esa variable (o fuera de MySQL, p. ej. en CI con SQLite) la prueba se omite.
 */
class ConcurrenciaPagoMysqlTest extends TestCase
{
    use Escenarios;

    public function test_dos_registros_simultaneos_del_mismo_pago_dejan_uno(): void
    {
        $dedicada = (string) getenv('CONCURRENCIA_DB');
        if ($dedicada === '') {
            $this->markTestSkipped('Prueba de concurrencia real: definí CONCURRENCIA_DB con una base MySQL dedicada.');
        }
        $original = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => $dedicada]);
        DB::purge();

        try {
            if (DB::getDriverName() !== 'mysql') {
                $this->markTestSkipped('La concurrencia real se prueba solo contra MySQL.');
            }
            Artisan::call('migrate:fresh', ['--force' => true]);

            // Datos confirmados (no hay transacción de prueba): los ven los dos procesos.
            $admin = $this->admin();
            $bloque = $this->bloque($this->sede('Palomar'));
            $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
            $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true]);

            $validado = [
                'fecha_pago' => now()->toDateString(),
                'monto_total' => 24000,
                'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
                'liquidar_profesor' => '1',
            ];
            $arranque = microtime(true) + 6.0; // tiempo de arranque de los dos procesos

            $entorno = array_merge(getenv(), ['DB_DATABASE' => $dedicada, 'DB_CONNECTION' => 'mysql']);
            $procesos = [];
            foreach ([1, 2] as $_) {
                $payload = json_encode(['arranque' => $arranque, 'usuario_id' => $admin->id, 'validado' => $validado], JSON_THROW_ON_ERROR);
                $pipes = [];
                $proceso = proc_open([PHP_BINARY, base_path('tests/Support/ConcurrenciaPagoWorker.php'), $payload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $entorno);
                $this->assertIsResource($proceso, 'no se pudo lanzar el proceso de prueba');
                $procesos[] = [$proceso, $pipes];
            }

            $resultados = [];
            foreach ($procesos as [$proceso, $pipes]) {
                $salida = trim((string) stream_get_contents($pipes[1]));
                $error = trim((string) stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proceso);
                $resultados[] = $salida !== '' ? $salida : 'SIN SALIDA '.$error;
            }

            $ganadores = array_values(array_filter($resultados, fn (string $r) => str_starts_with($r, 'OK ')));
            $rechazados = array_values(array_filter($resultados, fn (string $r) => str_starts_with($r, 'ERROR ')));
            $this->assertCount(1, $ganadores, 'debe quedar exactamente un registro: '.implode(' | ', $resultados));
            $this->assertCount(1, $rechazados, 'el otro debe rechazarse: '.implode(' | ', $resultados));
            $this->assertStringContainsString('ValidationException', $rechazados[0]);

            // Lo que quedó confirmado en la base: un pago y una línea para ese alumno y cuota.
            $this->assertSame(1, Pago::query()->count());
            $this->assertSame(1, PagoDetalle::query()->where('alumno_id', $ana->id)->where('cuota_id', $cuota->id)->count());
        } finally {
            config(['database.connections.mysql.database' => $original]);
            DB::purge();
        }
    }
}
