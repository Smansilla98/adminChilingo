<?php

namespace Tests\Feature;

use App\Domain\Finanzas\MorosidadService;
use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class MorosidadTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Alumno con una cuota vencida hace $dias días (y opcionalmente pagada). */
    private function moroso(string $nombre, int $dias, bool $pagada = false, bool $conCuenta = true): Alumno
    {
        $persona = $this->persona($nombre);
        $bloque = $this->bloque($this->sede('Palomar'), 'Martes '.$nombre);
        $alumno = $this->inscribirAlumno($persona, $bloque);
        if ($conCuenta) {
            $this->usuario($nombre, $persona);
        }
        $cuota = Cuota::query()->create([
            'nombre' => 'Mensual', 'año' => (int) now()->year, 'mes' => 1, 'monto' => 10000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id,
            'activo' => true, 'fecha_vencimiento' => now()->subDays($dias)->toDateString(),
        ]);
        if ($pagada) {
            $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 10000]);
            $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 10000]);
        }

        return $alumno;
    }

    public function test_lista_de_morosos_con_antiguedad_y_filtro_por_tramo(): void
    {
        $this->moroso('Ana', 45);
        $this->moroso('Beto', 10);
        $this->moroso('Cami', 200, pagada: true);
        Sanctum::actingAs($this->admin());

        $r = $this->getJson('/api/v1/morosos')->assertOk()->assertJsonPath('total_saldo', 20000);
        $this->assertCount(2, $r->json('data'), 'Cami pagó: no figura');
    }

    public function test_tramos_y_filtro(): void
    {
        $this->moroso('Ana', 45);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/morosos')->assertOk()->assertJsonPath('data.0.tramo', '31-60')->assertJsonPath('data.0.dias_mora', 45);
        $this->getJson('/api/v1/morosos?tramo=%2B90')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('0-30', app(MorosidadService::class)->tramo(12));
        $this->assertSame('+90', app(MorosidadService::class)->tramo(91));
    }

    public function test_recordatorio_individual_no_se_repite_en_el_mes(): void
    {
        $ana = $this->moroso('Ana', 45);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/morosos/{$ana->id}/recordatorio", ['canales' => ['interna']])->assertOk()->assertJsonPath('data.estado', 'enviado');
        $this->postJson("/api/v1/morosos/{$ana->id}/recordatorio", ['canales' => ['interna']])->assertOk()->assertJsonPath('data.estado', 'duplicado');
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('notificacion_envios')->where('clave', 'like', 'interna:morosidad:'.$ana->id.':%')->count());
    }

    public function test_masivo_cuenta_enviados_sin_cuenta_y_al_dia(): void
    {
        $ana = $this->moroso('Ana', 45);
        $sinCuenta = $this->moroso('Sofi', 45, conCuenta: false);
        $alDia = $this->moroso('Lu', 45, pagada: true);
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/morosos/recordatorios', ['alumno_ids' => [$ana->id, $sinCuenta->id, $alDia->id], 'canales' => ['interna']])
            ->assertOk()->assertJsonPath('data.enviado', 1)->assertJsonPath('data.sin_cuenta', 1)->assertJsonPath('data.al_dia', 1);
        $this->postJson('/api/v1/morosos/recordatorios', ['alumno_ids' => [$ana->id], 'canales' => ['interna']])
            ->assertOk()->assertJsonPath('data.duplicado', 1);
    }

    public function test_web_lista_y_recordatorio(): void
    {
        $ana = $this->moroso('Ana', 45);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('morosos.index'))->assertOk()->assertSee('Ana');
        $this->actingAs($admin)->post(route('morosos.recordar', $ana), ['canales' => ['interna']])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('morosos.recordatorios'), ['alumno_ids' => [$ana->id], 'canales' => ['interna']])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '0 enviados') || str_contains($m, 'ya enviados'));
    }
}
