<?php

namespace Tests\Feature;

use App\Domain\Finanzas\ComprobanteCuotaPdf;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ComprobanteCuotaPdfTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function comprobante(): ComprobanteCuotaAlumno
    {
        $bloque = $this->bloque($this->sede('Palomar'), 'Martes');
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true]);
        $c = ComprobanteCuotaAlumno::query()->create([
            'alumno_id' => $ana->id, 'sede_id' => $bloque->sede_id, 'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000, 'estado' => 'pendiente', 'referencia' => 'CH-ABC123',
        ]);
        ComprobanteCuotaAlumnoItem::query()->create(['comprobante_cuota_alumno_id' => $c->id, 'cuota_id' => $cuota->id, 'bloque_id' => $bloque->id, 'monto' => 24000]);

        return $c;
    }

    public function test_comprobante_de_cuota_en_pdf_web_y_api(): void
    {
        $c = $this->comprobante();
        $numero = ComprobanteCuotaPdf::numero($c);
        $admin = $this->admin();

        $web = $this->actingAs($admin)->get(route('comprobantes-cuota-alumnos.pdf', $c))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue(str_starts_with($web->getContent(), '%PDF'));
        $this->assertTrue(str_contains($web->getContent(), mb_convert_encoding($numero, 'UTF-16BE', 'UTF-8')), 'el PDF lleva el número del comprobante');

        Sanctum::actingAs($admin);
        $api = $this->get("/api/v1/comprobantes/{$c->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue(str_starts_with($api->getContent(), '%PDF'));
    }

    public function test_sin_permiso_de_comprobantes_es_403_y_la_ficha_enlaza_el_pdf(): void
    {
        $c = $this->comprobante();
        $sinPermiso = $this->usuario('Sin permisos');
        $this->actingAs($sinPermiso->fresh())->get(route('comprobantes-cuota-alumnos.pdf', $c))->assertForbidden();
        Sanctum::actingAs($sinPermiso->fresh());
        $this->get("/api/v1/comprobantes/{$c->id}/pdf")->assertForbidden();

        $this->actingAs($this->admin())->get(route('comprobantes-cuota-alumnos.show', $c))->assertOk()->assertSee(route('comprobantes-cuota-alumnos.pdf', $c), false);
    }
}
