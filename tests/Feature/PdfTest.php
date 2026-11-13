<?php

namespace Tests\Feature;

use App\Domain\Finanzas\ReciboPago;
use App\Models\Cuota;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function pago(): Pago
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 10000, 'notas' => 'Transferencia']);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 10000]);

        return $pago;
    }

    /**
     * Un PDF válido empieza con %PDF y su título (metadatos, en UTF-16BE) lleva el número de recibo.
     */
    private function assertPdfConRecibo(string $contenido, string $numero): void
    {
        $this->assertTrue(str_starts_with($contenido, '%PDF'), 'el contenido no es un PDF');
        $titulo = mb_convert_encoding($numero, 'UTF-16BE', 'UTF-8');
        $this->assertTrue(str_contains($contenido, $titulo), 'el PDF no lleva el número de recibo '.$numero);
    }

    public function test_recibo_del_pago_en_pdf_web_y_api(): void
    {
        $pago = $this->pago();
        $numero = ReciboPago::numero($pago);
        $this->assertSame('R-'.str_pad((string) $pago->id, 6, '0', STR_PAD_LEFT), $numero);

        $admin = $this->admin();
        $web = $this->actingAs($admin)->get(route('pagos.recibo', $pago))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertPdfConRecibo($web->getContent(), $numero);

        Sanctum::actingAs($admin);
        $api = $this->get("/api/v1/pagos/{$pago->id}/recibo")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertPdfConRecibo($api->getContent(), $numero);
    }

    public function test_recibo_sin_permiso_es_403(): void
    {
        $pago = $this->pago();
        $sinPermiso = $this->usuario('Sin permisos');
        $this->actingAs($sinPermiso->fresh())->get(route('pagos.recibo', $pago))->assertForbidden();
    }

    public function test_el_pago_muestra_el_enlace_al_recibo(): void
    {
        $pago = $this->pago();
        $this->actingAs($this->admin())->get(route('pagos.show', $pago))->assertOk()->assertSee(route('pagos.recibo', $pago), false);
    }
}
