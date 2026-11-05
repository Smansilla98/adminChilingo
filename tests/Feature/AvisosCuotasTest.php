<?php

namespace Tests\Feature;

use App\Domain\Notificaciones\ConfigAvisos;
use App\Models\Ajuste;
use App\Models\Beca;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Avisos de cuotas a los alumnos (nueva, por vencer) configurables desde Configuración,
 * y los datos para pagar que ve el alumno en la app.
 */
class AvisosCuotasTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function cuota(array $extra = []): Cuota
    {
        return Cuota::query()->create($extra + ['nombre' => 'Noviembre', 'año' => (int) now()->year, 'mes' => 11, 'monto' => 20000, 'alcance' => 'general', 'activo' => true]);
    }

    public function test_una_cuota_nueva_avisa_una_vez_con_monto_y_alias_y_no_a_becados(): void
    {
        Ajuste::guardar('cobro', ['alias' => 'chilinga.cuotas']);
        $bloque = $this->bloque($this->sede('Palomar'));
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $bloque);
        $becado = $this->usuario('Becado');
        $alumnoBecado = $this->inscribirAlumno($becado->persona, $bloque);
        Beca::query()->create(['alumno_id' => $alumnoBecado->id, 'tipo' => 'total', 'fecha_inicio' => now()->startOfYear(), 'estado' => 'activa']);
        $this->cuota(['fecha_vencimiento' => now()->addDays(10)]);

        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-nuevas'])->expectsOutput('Avisos nuevos: 1')->assertSuccessful();
        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-nuevas'])->expectsOutput('Avisos nuevos: 0')->assertSuccessful();

        $aviso = $alumna->fresh()->notifications()->first();
        $this->assertSame('Nueva cuota: Noviembre', $aviso->data['titulo']);
        $this->assertStringContainsString('$20.000', $aviso->data['mensaje']);
        $this->assertStringContainsString('chilinga.cuotas', $aviso->data['mensaje']);
        $this->assertSame('app://mi-cuenta', $aviso->data['enlace']);
        $this->assertSame(0, $becado->fresh()->notifications()->count());
    }

    public function test_el_admin_apaga_un_aviso_o_cambia_su_texto_y_canales(): void
    {
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $this->bloque($this->sede('Quilmes')));
        $this->cuota(['fecha_vencimiento' => now()->addDays(2)]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('configuracion.avisos.edit'))->assertOk()->assertSee('Cuota nueva para pagar');
        $this->actingAs($admin)->put(route('configuracion.avisos.update'), [
            'avisos' => [
                'cuota_nueva' => ['activo' => 0, 'canales' => ['interna']],
                'cuota_por_vencer' => ['activo' => 1, 'canales' => ['interna'], 'titulo' => 'Ojo: {cuota}', 'mensaje' => 'Faltan {dias} días.', 'dias' => 5],
            ],
            'cobro' => ['alias' => 'nuevo.alias'],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(ConfigAvisos::de('cuota_nueva')['activo']);
        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-nuevas'])->expectsOutput('Avisos nuevos: 0');
        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-por-vencer'])->expectsOutput('Avisos nuevos: 1');
        $aviso = $alumna->fresh()->notifications()->first();
        $this->assertSame('Ojo: Noviembre', $aviso->data['titulo']);
        $this->assertSame('Faltan 2 días.', $aviso->data['mensaje']);
    }

    public function test_desmarcar_todos_los_canales_en_la_web_los_apaga(): void
    {
        $this->actingAs($this->admin())->put(route('configuracion.avisos.update'), [
            'avisos' => ['cuota_vencida' => ['activo' => 1, 'canales' => ['']]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], ConfigAvisos::de('cuota_vencida')['canales']);
        $this->assertSame(['interna', 'push'], ConfigAvisos::de('cuota_nueva')['canales']);
    }

    public function test_el_alumno_ve_en_la_app_los_datos_para_pagar(): void
    {
        config(['chilinga.cobro.alias' => 'alias.del.env', 'chilinga.cobro.titular' => 'Asociación Civil']);
        Ajuste::guardar('cobro', ['alias' => 'alias.de.configuracion']);
        $user = $this->usuario('Alumna');
        $this->inscribirAlumno($user->persona, $this->bloque($this->sede('Banfield')));
        $this->cuota();
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/mi/estado-cuenta')->assertOk()
            ->assertJsonPath('cobro.transferencia.alias', 'alias.de.configuracion')
            ->assertJsonPath('cobro.transferencia.titular', 'Asociación Civil');
        $this->getJson('/api/v1/mi/cobro')->assertOk()->assertJsonPath('alumnos.0.pendientes.0.cuota', 'Noviembre');
        $this->postJson('/api/v1/mi/cobro/mercadopago', ['alumno_id' => 999999])->assertStatus(422); // sin Mercado Pago configurado
    }

    public function test_solo_quien_envia_avisos_configura_desde_la_app(): void
    {
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $this->bloque($this->sede('Varela')));
        Sanctum::actingAs($alumna->fresh());
        $this->getJson('/api/v1/config/avisos')->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/config/avisos', ['avisos' => ['pago_registrado' => ['activo' => true, 'canales' => ['interna', 'push']]]])
            ->assertOk()->assertJsonPath('avisos.pago_registrado.canales', ['interna', 'push'])
            ->assertJsonPath('avisos.cuota_nueva.activo', true); // lo que no se manda queda como estaba
    }
}
