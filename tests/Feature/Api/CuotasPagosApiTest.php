<?php

namespace Tests\Feature\Api;

use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class CuotasPagosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function tesorero(?Sede $sede = null)
    {
        $u = $this->usuario('Tesorería');
        $this->asignarRol($u->persona, 'tesorero', $sede ? 'sede' : 'global', $sede);

        return $u->fresh();
    }

    private function cuota(array $datos = []): Cuota
    {
        return Cuota::query()->create($datos + ['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'general', 'activo' => true]);
    }

    // ───────── Cuotas ─────────

    public function test_cuotas_alta_con_alcance_y_unicidad_por_periodo(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $anio = (int) now()->year;
        Sanctum::actingAs($this->tesorero($palomar));
        $base = ['nombre' => 'Abril', 'anio' => $anio, 'mes' => 4, 'monto' => 25000];

        $this->postJson('/api/v1/cuotas', $base + ['alcance' => 'general'])->assertForbidden();
        $this->postJson('/api/v1/cuotas', $base + ['alcance' => 'sede', 'sede_id' => $varela->id])->assertForbidden();
        $this->postJson('/api/v1/cuotas', $base + ['alcance' => 'sede'])->assertStatus(422)->assertJsonValidationErrors('sede_id');

        $id = $this->postJson('/api/v1/cuotas', $base + ['alcance' => 'sede', 'sede_id' => $palomar->id])->assertCreated()
            ->assertJsonPath('data.alcance', 'sede')->assertJsonPath('data.anio', $anio)->assertJsonPath('data.activo', true)->json('data.id');
        $this->postJson('/api/v1/cuotas', $base + ['alcance' => 'sede', 'sede_id' => $palomar->id])->assertStatus(422)->assertJsonValidationErrors('mes');

        // Desactivar sin tocar lo demás.
        $this->putJson("/api/v1/cuotas/{$id}", ['activo' => false] + $base + ['alcance' => 'sede', 'sede_id' => $palomar->id])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertSame(1, $this->getJson("/api/v1/cuotas?anio={$anio}&activo=0")->assertOk()->json('meta.total'));
    }

    public function test_cuota_con_pagos_no_se_elimina_y_sin_permiso_es_403(): void
    {
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);

        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());
        $this->getJson("/api/v1/cuotas/{$cuota->id}")->assertOk()->assertJsonPath('data.total_cobrado', 24000)->assertJsonPath('data.pagos', 1)->assertJsonPath('data.cobros.0.alumno.id', $alumno->id)->assertJsonPath('data.acciones.editar', false);
        $this->deleteJson("/api/v1/cuotas/{$cuota->id}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/v1/cuotas/{$cuota->id}")->assertStatus(422);
        $this->assertNotNull(Cuota::query()->find($cuota->id));
    }

    // ───────── Pagos ─────────

    public function test_registrar_pago_con_liquidacion_segun_regla_de_la_sede(): void
    {
        $sede = $this->sede('Banfield');
        $sede->forceFill(['liquidacion_retencion_escuela' => 14400, 'liquidacion_porc_docente' => 100])->save();
        $bloque = $this->bloque($sede);
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = $this->cuota(['alcance' => 'bloque', 'bloque_id' => $bloque->id]);
        Sanctum::actingAs($this->tesorero());

        $this->assertSame([$alumno->id], collect($this->getJson("/api/v1/pagos/cuotas/{$cuota->id}/alumnos")->assertOk()->json('data'))->pluck('id')->all());

        $r = $this->postJson('/api/v1/pagos', [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
            'notas' => 'Transferencia',
        ])->assertCreated();

        $this->assertSame(9600, (int) $r->json('data.detalles.0.abono_profesor'));
        $this->assertSame(9600, (int) $r->json('data.total_abono_profesor'));
        $this->assertTrue($r->json('data.acciones.anular'));
        $this->assertSame([], $this->getJson("/api/v1/pagos/cuotas/{$cuota->id}/alumnos")->json('data'), 'ya pagó: no se ofrece de nuevo');
        $this->assertSame(0, (int) $this->getJson("/api/v1/alumnos/{$alumno->id}/estado-cuenta")->json('totales.saldo'));
    }

    public function test_abono_manual_se_reparte_en_proporcion_a_cada_linea(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $beto = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());

        $r = $this->postJson('/api/v1/pagos', [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 30000,
            'monto_abono_profesor' => 1000,
            'lineas' => [
                ['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 20000],
                ['alumno_id' => $beto->id, 'cuota_id' => $cuota->id, 'monto' => 10000],
            ],
        ])->assertCreated();

        $this->assertEqualsWithDelta(666.66, $r->json('data.detalles.0.abono_profesor'), 0.001);
        $this->assertEqualsWithDelta(333.34, $r->json('data.detalles.1.abono_profesor'), 0.001);
    }

    public function test_validaciones_de_negocio_del_pago(): void
    {
        $palomar = $this->sede('Palomar');
        $bloque = $this->bloque($palomar);
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $otroBloque = $this->bloque($palomar, 'Otro');
        $cuotaDeOtroBloque = $this->cuota(['alcance' => 'bloque', 'bloque_id' => $otroBloque->id, 'mes' => 5]);
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());
        $linea = ['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000];
        $base = ['fecha_pago' => now()->toDateString()];

        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 20000, 'lineas' => [$linea]])->assertStatus(422)->assertJsonValidationErrors('monto_total');
        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 48000, 'lineas' => [$linea, $linea]])->assertStatus(422)->assertJsonValidationErrors('lineas');
        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 24000, 'lineas' => [['cuota_id' => $cuotaDeOtroBloque->id] + $linea]])
            ->assertStatus(422)->assertJsonValidationErrors('lineas.0.alumno_id');
        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 24000, 'lineas' => []])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 24000, 'lineas' => [$linea]])->assertCreated();
        $this->postJson('/api/v1/pagos', $base + ['monto_total' => 24000, 'lineas' => [$linea]])->assertStatus(422)->assertJsonValidationErrors('lineas.0.alumno_id');
        $this->assertSame(1, Pago::query()->count());
    }

    public function test_no_se_cobra_a_alumnos_fuera_del_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $ajeno = $this->inscribirAlumno($this->persona('Ajena'), $this->bloque($this->sede('Varela')));
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero($palomar));

        $this->postJson('/api/v1/pagos', ['fecha_pago' => now()->toDateString(), 'monto_total' => 24000, 'lineas' => [['alumno_id' => $ajeno->id, 'cuota_id' => $cuota->id, 'monto' => 24000]]])
            ->assertForbidden();
        $this->assertSame(0, Pago::query()->count());

        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $this->bloque($palomar));
        Sanctum::actingAs($profe->fresh());
        $this->postJson('/api/v1/pagos', [])->assertForbidden();
    }

    public function test_reintento_con_el_mismo_client_uuid_no_duplica_el_pago(): void
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());
        $datos = ['client_uuid' => (string) Str::uuid(), 'fecha_pago' => now()->toDateString(), 'monto_total' => 24000, 'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]]];

        $id = $this->postJson('/api/v1/pagos', $datos)->assertCreated()->assertJsonPath('duplicado', false)->json('data.id');
        $this->postJson('/api/v1/pagos', $datos)->assertOk()->assertJsonPath('duplicado', true)->assertJsonPath('data.id', $id);
        $this->assertSame(1, Pago::query()->count());
    }

    public function test_comprobante_multipart_edicion_y_anulacion(): void
    {
        Storage::fake('comprobantes');
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());

        $id = $this->post('/api/v1/pagos', [
            'fecha_pago' => now()->toDateString(), 'monto_total' => 24000, 'liquidar_profesor' => '0',
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
            'comprobante' => UploadedFile::fake()->create('transferencia.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.tiene_comprobante', true)->json('data.id');

        $this->get("/api/v1/pagos/{$id}/comprobante")->assertOk()->assertHeader('content-disposition');
        $this->putJson("/api/v1/pagos/{$id}", ['fecha_pago' => now()->toDateString(), 'monto_total' => 20000, 'quitar_comprobante' => true, 'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 20000]]])
            ->assertOk()->assertJsonPath('monto_total', null)->assertJsonPath('data.monto_total', 20000)->assertJsonPath('data.tiene_comprobante', false);
        $this->assertSame([], Storage::disk('comprobantes')->allFiles('pagos'), 'el archivo quitado se borra');

        $this->postJson("/api/v1/pagos/{$id}/anular", ['motivo' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/pagos/{$id}/anular", ['motivo' => 'Transferencia rechazada'])->assertOk()->assertJsonPath('data.anulado', true)->assertJsonPath('data.acciones.editar', false);
        $this->putJson("/api/v1/pagos/{$id}", ['fecha_pago' => now()->toDateString(), 'monto_total' => 1, 'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 1]]])->assertForbidden();
        $this->assertSame(1, $this->getJson('/api/v1/pagos?estado=anulados')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/pagos?estado=vigentes')->json('meta.total'));
    }

    public function test_docente_ve_los_pagos_de_sus_alumnos_con_su_abono(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $ajena = $this->inscribirAlumno($this->persona('Ajena'), $this->bloque($sede, 'Otro'));
        $cuota = $this->cuota(['alcance' => 'bloque', 'bloque_id' => $bloque->id]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        PagoDetalle::query()->create(['pago_id' => $pago->id, 'alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000, 'abono_profesor' => 9600]);
        $cuotaOtro = $this->cuota(['alcance' => 'bloque', 'bloque_id' => $ajena->bloque_id, 'mes' => 6]);
        $otro = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        PagoDetalle::query()->create(['pago_id' => $otro->id, 'alumno_id' => $ajena->id, 'cuota_id' => $cuotaOtro->id, 'monto' => 24000, 'abono_profesor' => 9600]);

        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        Sanctum::actingAs($profe->fresh());

        $r = $this->getJson('/api/v1/mi/pagos-docente')->assertOk();
        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame('Ana', $r->json('data.0.alumno'));
        $this->assertEquals(9600, $r->json('total_abono'));
    }
}
