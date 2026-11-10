<?php

namespace Tests\Feature;

use App\Domain\Datos\CifradoRespaldo;
use App\Domain\Datos\PruebaRestauracion;
use App\Domain\Datos\Respaldo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Copia cifrada del backup fuera del servidor (S3/R2), retención y prueba de restauración.
 */
class RespaldoExternoTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private const CLAVE = 'una-clave-larga-de-prueba-123';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
        Storage::fake('s3');
    }

    private function configurarExterno(int $conservar = 30): void
    {
        config([
            'operacion.respaldo.disco_externo' => 's3',
            'operacion.respaldo.clave' => self::CLAVE,
            'operacion.respaldo.conservar_externo' => $conservar,
        ]);
    }

    public function test_sin_disco_externo_el_backup_sigue_local_y_avisa(): void
    {
        $this->artisan('chilinga:respaldo')
            ->expectsOutputToContain('Sin copia fuera del servidor')
            ->assertSuccessful();

        $this->assertCount(1, Respaldo::archivos());
        $this->assertSame([], Storage::disk('s3')->allFiles());
        $this->assertNull(Respaldo::ultimo()['externo']);
    }

    public function test_sin_clave_no_sube_nada_sin_cifrar(): void
    {
        config(['operacion.respaldo.disco_externo' => 's3', 'operacion.respaldo.clave' => null]);

        $this->artisan('chilinga:respaldo')->assertSuccessful();

        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_sube_copia_cifrada_que_se_descifra_y_poda_las_viejas(): void
    {
        $this->sede('Palomar');
        $this->configurarExterno(conservar: 2);

        foreach (range(1, 3) as $_) {
            $this->artisan('chilinga:respaldo')->assertSuccessful();
            $this->travel(1)->days();
        }

        $copias = Respaldo::archivosExternos();
        $this->assertCount(2, $copias);
        $this->assertStringEndsWith('.sql.gz.enc', $copias[0]);
        $this->assertTrue(Respaldo::ultimo()['externo']['ok']);

        $cifrado = Storage::disk('s3')->get($copias[0]);
        $this->assertStringStartsWith('Salted__', $cifrado);
        $this->assertStringNotContainsString('Palomar', $cifrado);

        $enc = tempnam(sys_get_temp_dir(), 't');
        $gz = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($enc, $cifrado);
        CifradoRespaldo::descifrar($enc, $gz, self::CLAVE);
        $sql = (string) gzdecode((string) file_get_contents($gz));
        @unlink($enc);
        @unlink($gz);

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('Palomar', $sql);
    }

    public function test_si_falla_la_copia_externa_el_comando_falla_pero_el_local_queda(): void
    {
        config(['filesystems.disks.roto' => ['driver' => 'local', 'root' => '/proc/no-se-puede-escribir']]);
        config(['operacion.respaldo.disco_externo' => 'roto', 'operacion.respaldo.clave' => self::CLAVE]);

        $this->artisan('chilinga:respaldo')->assertFailed();

        $this->assertCount(1, Respaldo::archivos());
        $ultimo = Respaldo::ultimo();
        $this->assertTrue($ultimo['ok']);
        $this->assertFalse($ultimo['externo']['ok']);
    }

    public function test_la_clave_equivocada_no_descifra(): void
    {
        $plano = tempnam(sys_get_temp_dir(), 't');
        $enc = tempnam(sys_get_temp_dir(), 't');
        $out = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($plano, str_repeat('datos personales ', 5000));
        CifradoRespaldo::cifrar($plano, $enc, self::CLAVE);

        try {
            CifradoRespaldo::descifrar($enc, $out, 'otra-clave-que-no-es-la-buena');
            $this->fail('Descifró con la clave equivocada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('clave', $e->getMessage());
        } finally {
            @unlink($plano);
            @unlink($enc);
            @unlink($out);
        }
    }

    public function test_restaura_el_ultimo_backup_local_y_externo_en_una_base_temporal(): void
    {
        $this->sede('Palomar');
        $this->configurarExterno();
        $this->artisan('chilinga:respaldo')->assertSuccessful();

        $this->artisan('chilinga:respaldo:probar')
            ->expectsOutputToContain('Restauración OK')
            ->assertSuccessful();
        $this->artisan('chilinga:respaldo:probar', ['--externo' => true])
            ->expectsOutputToContain('Restauración OK')
            ->assertSuccessful();

        // La base real no se tocó.
        $this->assertDatabaseHas('sedes', ['nombre' => 'Palomar']);
    }

    public function test_el_separador_respeta_punto_y_coma_dentro_de_textos(): void
    {
        $gz = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($gz, gzencode(implode("\n", [
            '-- Backup La Chilinga · 2026-11-10T00:00:00-03:00 · mysql',
            'SET NAMES utf8mb4;',
            'INSERT INTO `x` (`a`) VALUES',
            "('hola;', 'chau\\';'),",
            "('dos');",
            '',
        ])));

        $sentencias = iterator_to_array((new PruebaRestauracion)->sentencias($gz, 'mysql'), false);
        @unlink($gz);

        $this->assertCount(2, $sentencias);
        $this->assertStringContainsString("('dos')", $sentencias[1]);

        // SQLite guarda saltos de línea crudos dentro de los textos.
        $gz = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($gz, gzencode(implode("\n", [
            '-- Backup La Chilinga · 2026-11-10T00:00:00-03:00 · sqlite',
            'INSERT INTO "x" ("a") VALUES',
            "('termina en punto y coma;",
            "y sigue acá', 'it''s;');",
            'SELECT 1;',
            '',
        ])));
        $sentencias = iterator_to_array((new PruebaRestauracion)->sentencias($gz, 'sqlite'), false);
        @unlink($gz);

        $this->assertCount(2, $sentencias);
        $this->assertStringContainsString('y sigue acá', $sentencias[0]);
    }
}
