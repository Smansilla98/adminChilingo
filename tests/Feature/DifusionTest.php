<?php

namespace Tests\Feature;

use App\Domain\Notificaciones\DifusionService;
use App\Models\Cuota;
use App\Models\Difusion;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Mensajes a grupos: destinatarios, alcance, programados, límite de WhatsApp y lecturas. */
class DifusionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_manda_a_una_sede_y_ve_cuantos_recibieron_y_leyeron(): void
    {
        $palomar = $this->sede('Palomar');
        $bloque = $this->bloque($palomar, 'Bloque A');
        $ana = $this->usuario('Ana');
        $this->inscribirAlumno($ana->persona, $bloque);
        $beto = $this->usuario('Beto');
        $this->inscribirAlumno($beto->persona, $bloque);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $ajena = $this->usuario('Ajena');
        $this->inscribirAlumno($ajena->persona, $this->bloque($this->sede('Varela')));
        Sanctum::actingAs($this->admin());

        $base = ['alcance' => 'sedes', 'sede_ids' => [$palomar->id], 'canales' => ['interna'], 'alumnos' => true, 'docentes' => true];
        $this->postJson('/api/v1/difusiones/vista-previa', $base)->assertOk()->assertJsonPath('data.cantidad', 3);

        $id = $this->postJson('/api/v1/difusiones', $base + ['titulo' => 'Feriado', 'mensaje' => 'El lunes no hay clase.', 'enlace' => 'app://agenda'])
            ->assertCreated()->assertJsonPath('data.estado', 'enviada')->assertJsonPath('data.destinatarios', 3)
            ->assertJsonPath('data.resultado.interna', 3)->json('data.id');

        $this->assertSame(1, $ana->fresh()->notifications()->count());
        $this->assertSame(0, $ajena->fresh()->notifications()->count());
        $ana->fresh()->notifications()->first()->markAsRead();

        $this->getJson("/api/v1/difusiones/{$id}")->assertOk()
            ->assertJsonPath('data.lecturas.recibieron', 3)->assertJsonPath('data.lecturas.leyeron', 1);
        $this->assertSame(1, $this->getJson('/api/v1/difusiones')->json('meta.total'));

        // No se repite aunque se intente mandar de nuevo.
        app(DifusionService::class)->enviar(Difusion::query()->find($id));
        $this->assertSame(1, $ana->fresh()->notifications()->count());
    }

    public function test_coordinador_solo_llega_a_su_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $propio = $this->bloque($palomar);
        $ajeno = $this->bloque($varela);
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());
        $base = ['titulo' => 'Hola', 'mensaje' => 'Texto', 'canales' => ['interna'], 'alumnos' => true];

        $this->postJson('/api/v1/difusiones', $base + ['alcance' => 'escuela'])->assertForbidden();
        $this->postJson('/api/v1/difusiones', $base + ['alcance' => 'sedes', 'sede_ids' => [$varela->id]])->assertForbidden();
        $this->postJson('/api/v1/difusiones', $base + ['alcance' => 'bloques', 'bloque_ids' => [$ajeno->id]])->assertForbidden();
        $this->postJson('/api/v1/difusiones', $base + ['alcance' => 'bloques', 'bloque_ids' => [$propio->id]])->assertCreated();

        $cat = $this->getJson('/api/v1/difusiones/catalogo')->assertOk();
        $this->assertArrayNotHasKey('escuela', $cat->json('alcances'));
        $this->assertSame([$palomar->id], collect($cat->json('sedes'))->pluck('id')->all());
    }

    public function test_sin_permiso_es_403(): void
    {
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $this->bloque($this->sede('Palomar')));
        Sanctum::actingAs($profe->fresh());
        $this->getJson('/api/v1/difusiones')->assertForbidden();
        $this->actingAs($profe->fresh())->get('/difusion')->assertForbidden();
    }

    public function test_programada_sale_con_el_scheduler_y_se_puede_cancelar(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->usuario('Ana');
        $this->inscribirAlumno($ana->persona, $bloque);
        Sanctum::actingAs($this->admin());
        $base = ['titulo' => 'Recordatorio', 'mensaje' => 'Mañana ensayo', 'canales' => ['interna'], 'alcance' => 'escuela', 'alumnos' => true];

        $id = $this->postJson('/api/v1/difusiones', $base + ['programada_para' => now()->addHour()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.estado', 'programada')->json('data.id');
        $otra = $this->postJson('/api/v1/difusiones', $base + ['programada_para' => now()->addHours(2)->toDateTimeString()])->json('data.id');
        $this->postJson("/api/v1/difusiones/{$otra}/cancelar")->assertOk()->assertJsonPath('data.estado', 'cancelada');

        $this->artisan('chilinga:difusiones')->assertSuccessful();
        $this->assertSame(0, $ana->fresh()->notifications()->count(), 'todavía no es la hora');

        Carbon::setTestNow(now()->addHours(3));
        $this->artisan('chilinga:difusiones')->assertSuccessful();
        $this->assertSame(1, $ana->fresh()->notifications()->count());
        $this->assertSame('enviada', Difusion::query()->find($id)->estado);
        $this->assertSame('cancelada', Difusion::query()->find($otra)->estado);
    }

    public function test_whatsapp_pide_confirmacion_si_son_muchos(): void
    {
        config(['chilinga.difusion.whatsapp_confirmar_desde' => 2, 'chilinga.difusion.whatsapp_maximo' => 3]);
        $bloque = $this->bloque($this->sede('Palomar'));
        foreach (['A', 'B'] as $n) {
            $u = $this->usuario($n);
            $u->persona->update(['telefono' => '11555000'.ord($n)]);
            $this->inscribirAlumno($u->persona, $bloque);
        }
        Sanctum::actingAs($this->admin());
        $base = ['titulo' => 'Hola', 'mensaje' => 'Texto', 'canales' => ['interna', 'whatsapp'], 'alcance' => 'bloques', 'bloque_ids' => [$bloque->id], 'alumnos' => true];

        $this->postJson('/api/v1/difusiones/vista-previa', $base)->assertOk()->assertJsonPath('data.whatsapp.requiere_confirmacion', true);
        $this->postJson('/api/v1/difusiones', $base)->assertStatus(422)->assertJsonValidationErrors('confirmar_whatsapp');
        $this->postJson('/api/v1/difusiones', $base + ['confirmar_whatsapp' => true])->assertCreated();
    }

    public function test_alumnos_con_deuda_y_convocados_a_un_show(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $debe = $this->usuario('Debe');
        $this->inscribirAlumno($debe->persona, $bloque);
        $otroBloque = $this->bloque($this->sede('Palomar'), 'Otro');
        $alDia = $this->usuario('Al día');
        $this->inscribirAlumno($alDia->persona, $otroBloque);
        Cuota::query()->create([
            'nombre' => 'Marzo', 'monto' => 1000, 'mes' => 3, 'año' => (int) now()->year,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(), 'activo' => true, 'bloque_id' => $bloque->id,
        ]);
        $show = Show::query()->create(['titulo' => 'Carnaval', 'fecha' => now()->addWeek()->toDateString()]);
        $show->bloques()->sync([$otroBloque->id]);

        $servicio = app(DifusionService::class);
        $deuda = DifusionService::filtros(['alcance' => 'escuela', 'alumnos' => true, 'solo_con_deuda' => true]);
        $this->assertSame([$debe->id], $servicio->destinatarios($deuda)->pluck('id')->all());
        $delShow = DifusionService::filtros(['alcance' => 'show', 'show_id' => $show->id, 'alumnos' => true]);
        $this->assertSame([$alDia->id], $servicio->destinatarios($delShow)->pluck('id')->all());
    }

    public function test_pantallas_web(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/difusion')->assertOk()->assertSee('Mensajes a grupos');
        $this->actingAs($admin)->get('/difusion/crear')->assertOk()->assertSee('Ver a cuántos les llega');
        $this->actingAs($admin)->post('/difusion', [
            'titulo' => 'Hola', 'mensaje' => 'Texto', 'canales' => ['interna'], 'alcance' => 'escuela', 'alumnos' => '1', 'docentes' => '0',
        ])->assertRedirect();
        $d = Difusion::query()->firstOrFail();
        $this->actingAs($admin)->get("/difusion/{$d->id}")->assertOk()->assertSee('Hola');
    }
}
