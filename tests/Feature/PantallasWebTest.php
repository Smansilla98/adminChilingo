<?php

namespace Tests\Feature;

use App\Models\Beca;
use App\Models\Cuota;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Las pantallas nuevas y las modificadas renderizan sin errores, con datos reales.
 */
class PantallasWebTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_pantallas_de_administracion_renderizan(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Banfield');
        $bloque = $this->bloque($sede);
        $maria = $this->usuario('María Gómez');
        $alumno = $this->inscribirAlumno($maria->persona, $bloque);
        $this->asignarDocente($maria->persona, $this->bloque($sede, 'Otro'));
        $this->asignarRol($maria->persona, 'contador');
        Beca::query()->create(['alumno_id' => $alumno->id, 'tipo' => 'porcentaje', 'porcentaje' => 30, 'fecha_inicio' => now()->startOfYear(), 'estado' => 'activa']);
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 700]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 700]);

        $this->actingAs($admin);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('personas.index', ['q' => 'María']))->assertOk()->assertSee('María Gómez');
        $this->get(route('personas.show', $maria->persona))->assertOk()
            ->assertSee('Funciones')->assertSee('Contador')->assertSee('Beca 30%')->assertSee('Qué puede hacer');
        $this->get(route('personas.create'))->assertOk();
        $this->get(route('personas.edit', $maria->persona))->assertOk();
        $this->get(route('usuarios.index'))->assertOk()->assertSee($maria->username);
        $this->get(route('usuarios.show', $maria))->assertOk()->assertSee('Roles y alcance')->assertSee('Ver pagos');
        $this->get(route('usuarios.create', ['persona_id' => $maria->persona_id]))->assertOk();
        $this->get(route('auditoria.index'))->assertOk();
        $this->get(route('alumnos.create', ['persona_id' => $maria->persona_id]))->assertOk();
        $this->get(route('profesores.create', ['persona_id' => $maria->persona_id]))->assertOk();
        $this->get(route('pagos.show', $pago))->assertOk()->assertSee('Anular');
        $this->get(route('accesos.index'))->assertOk();
    }

    public function test_persona_multirrol_ve_su_espacio_docente_y_gestion_en_el_menu(): void
    {
        $sede = $this->sede('Palomar');
        $user = $this->usuario('Juan');
        $this->asignarDocente($user->persona, $this->bloque($sede));
        $this->asignarRol($user->persona, 'contador');

        $r = $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
        $r->assertSee('Docente')->assertSee('Pagos')->assertSee('Estoy trabajando como');

        // Cambiar de contexto solo cambia la vista inicial.
        $this->post(route('contexto.cambiar'), ['contexto' => 'profesor:'.$sede->id])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('pagos.index'))->assertOk();
    }

    public function test_alumno_ve_su_ficha_pero_no_la_de_otros(): void
    {
        $sede = $this->sede('Quilmes');
        $user = $this->usuario('Alumna');
        $this->inscribirAlumno($user->persona, $this->bloque($sede));
        $otra = $this->persona('Otra');

        $this->actingAs($user->fresh())->get(route('personas.show', $user->persona))->assertOk();
        $this->actingAs($user->fresh())->get(route('personas.show', $otra))->assertForbidden();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }
}
