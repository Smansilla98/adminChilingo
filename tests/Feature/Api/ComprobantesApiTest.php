<?php

namespace Tests\Feature\Api;

use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ComprobantesApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
    }

    private function escenario(): array
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $user = $this->usuario('Ana');
        $alumno = $this->inscribirAlumno($user->persona, $bloque);
        $cuota = Cuota::query()->create(['nombre' => 'Agosto', 'año' => 2026, 'mes' => 8, 'monto' => 20000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true]);

        return compact('sede', 'bloque', 'user', 'alumno', 'cuota');
    }

    private function envio(int $alumnoId, int $bloqueId): array
    {
        return ['alumno_id' => $alumnoId, 'anio' => 2026, 'mes' => 8, 'fecha_pago' => '2026-08-05', 'bloque_ids' => [$bloqueId], 'comprobante' => UploadedFile::fake()->image('transferencia.jpg')];
    }

    public function test_el_alumno_envia_su_comprobante_desde_la_app_y_no_el_de_otro(): void
    {
        ['bloque' => $bloque, 'user' => $user, 'alumno' => $alumno] = $this->escenario();
        $otro = $this->inscribirAlumno($this->persona('Otro'), $bloque);
        Sanctum::actingAs($user->fresh());

        $op = $this->getJson("/api/v1/comprobantes/opciones?alumno_id={$alumno->id}&anio=2026&mes=8")->assertOk();
        $this->assertSame(20000.0, (float) $op->json('bloques.0.monto'));
        $this->getJson("/api/v1/comprobantes/opciones?alumno_id={$otro->id}&anio=2026&mes=8")->assertForbidden();

        $this->post('/api/v1/mi/comprobantes', $this->envio($otro->id, $bloque->id), ['Accept' => 'application/json'])->assertForbidden();
        $id = $this->post('/api/v1/mi/comprobantes', $this->envio($alumno->id, $bloque->id), ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.monto_total', 20000)->json('data.id');

        $this->getJson('/api/v1/mi/comprobantes')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v1/comprobantes/{$id}")->assertOk()->assertJsonPath('data.acciones.aprobar', false);
        $this->get("/api/v1/comprobantes/{$id}/archivo")->assertOk();
        $this->getJson('/api/v1/comprobantes')->assertForbidden(); // la gestión no es para alumnos
        $this->post('/api/v1/mi/comprobantes', ['mes' => 13] + $this->envio($alumno->id, $bloque->id), ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_tesoreria_aprueba_y_registra_el_pago_una_sola_vez(): void
    {
        ['bloque' => $bloque, 'user' => $user, 'alumno' => $alumno] = $this->escenario();
        Sanctum::actingAs($user->fresh());
        $id = $this->post('/api/v1/mi/comprobantes', $this->envio($alumno->id, $bloque->id), ['Accept' => 'application/json'])->json('data.id');

        $tesorero = $this->usuario('Tesorería');
        $this->asignarRol($tesorero->persona, 'tesorero');
        Sanctum::actingAs($tesorero->fresh());
        $this->assertSame(1, $this->getJson('/api/v1/comprobantes?estado=pendiente')->assertOk()->json('pendientes'));

        $r = $this->postJson("/api/v1/comprobantes/{$id}/aprobar")->assertOk();
        $pago = Pago::query()->findOrFail($r->json('pago_id'));
        $this->assertEquals(20000, $pago->monto_total);
        $this->assertSame('pagado', $r->json('data.estado'));
        $this->postJson("/api/v1/comprobantes/{$id}/aprobar")->assertStatus(409);
        $this->assertSame(1, Pago::query()->count());
        $this->assertSame(0, (int) $this->getJson("/api/v1/alumnos/{$alumno->id}/estado-cuenta?anio=2026")->json('totales.saldo'));
    }

    public function test_docente_carga_y_marca_visto_pero_no_aprueba(): void
    {
        ['sede' => $sede, 'bloque' => $bloque, 'alumno' => $alumno] = $this->escenario();
        $ajeno = $this->inscribirAlumno($this->persona('Ajena'), $this->bloque($this->sede('Varela')));
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        Sanctum::actingAs($profe->fresh());

        $this->post('/api/v1/comprobantes', $this->envio($ajeno->id, $ajeno->bloque_id), ['Accept' => 'application/json'])->assertForbidden();
        $id = $this->post('/api/v1/comprobantes', $this->envio($alumno->id, $bloque->id), ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/comprobantes/{$id}/aprobar")->assertForbidden();
        $this->postJson("/api/v1/comprobantes/{$id}/visto")->assertOk()->assertJsonPath('data.estado', 'visto');
        $this->assertSame('visto', ComprobanteCuotaAlumno::query()->find($id)->estado);
        $this->assertNotNull($sede);
    }
}
