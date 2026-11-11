<?php

namespace App\Console\Commands;

use App\Domain\Datos\CifradoRespaldo;
use App\Domain\Datos\PruebaRestauracion;
use App\Domain\Datos\Respaldo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RespaldoProbarCommand extends Command
{
    protected $signature = 'chilinga:respaldo:probar
        {--externo : Probar la última copia cifrada del disco externo (la descarga y descifra con RESPALDO_CLAVE)}
        {--archivo= : Ruta dentro del disco (respaldos/... o la carpeta externa); por defecto el último}
        {--conservar-base : No borrar la base temporal al terminar (para revisarla a mano)}';

    protected $description = 'Restaura un backup en una base temporal y verifica que esté completo (no toca la base real).';

    public function handle(PruebaRestauracion $prueba): int
    {
        $temporales = [];
        try {
            $sqlGz = $this->option('externo') ? $this->bajarExterno($temporales) : $this->bajarLocal($temporales);
            $r = $prueba->probar($sqlGz, (bool) $this->option('conservar-base'));
        } catch (Throwable $e) {
            $this->error('La prueba de restauración falló: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        } finally {
            foreach ($temporales as $t) {
                @unlink($t);
            }
        }

        $this->info(sprintf(
            'Restauración OK en %s: %d tablas (de %d), %d filas, %d migraciones, %d sentencias.',
            $r['base'], $r['tablas'], $r['tablas_esperadas'], $r['filas'], $r['migraciones'], $r['sentencias'],
        ));
        $this->line($r['conservada'] ? "La base temporal {$r['base']} quedó creada: borrala cuando termines." : 'La base temporal ya se borró.');

        return self::SUCCESS;
    }

    /** @param  list<string>  $temporales */
    private function bajarLocal(array &$temporales): string
    {
        $archivo = $this->option('archivo') ?: (Respaldo::archivos()[0] ?? null);
        if (! $archivo) {
            throw new RuntimeException('No hay backups locales. Corré «php artisan chilinga:respaldo» primero.');
        }
        $this->line("Backup local: {$archivo}");

        return $this->copiar(Storage::disk('comprobantes')->readStream($archivo), $temporales);
    }

    /** @param  list<string>  $temporales */
    private function bajarExterno(array &$temporales): string
    {
        if (! Respaldo::externoConfigurado()) {
            throw new RuntimeException('Falta configurar RESPALDO_DISCO_EXTERNO y RESPALDO_CLAVE.');
        }
        $archivo = $this->option('archivo') ?: (Respaldo::archivosExternos()[0] ?? null);
        if (! $archivo) {
            throw new RuntimeException('No hay copias en el disco externo todavía.');
        }
        $this->line("Copia externa: {$archivo}");
        $cifrado = $this->copiar(Storage::disk((string) config('operacion.respaldo.disco_externo'))->readStream($archivo), $temporales);
        $sqlGz = $cifrado.'.sql.gz';
        $temporales[] = $sqlGz;
        CifradoRespaldo::descifrar($cifrado, $sqlGz, (string) config('operacion.respaldo.clave'));

        return $sqlGz;
    }

    /**
     * @param  resource|null  $stream
     * @param  list<string>  $temporales
     */
    private function copiar($stream, array &$temporales): string
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer el archivo del disco.');
        }
        $destino = (string) tempnam(sys_get_temp_dir(), 'prueba-respaldo-');
        $temporales[] = $destino;
        $out = fopen($destino, 'wb');
        if ($out === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal.');
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return $destino;
    }
}
