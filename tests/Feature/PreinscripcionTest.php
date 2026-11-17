<?php

namespace Tests\Feature;

use App\Domain\Personas\PreinscripcionService;
use App\Models\Alumno;
use App\Models\Preinscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Preinscripción pública con lista de espera y bandeja de aprobación. */
class PreinscripcionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function datos(array $extra = []): array
    {
        $sede = $this->sede('Sede Pre');
        $bloque = $this->bloque($sede, 'Martes 19h');

        return $extra + [
            'sede_id' => $sede->id, 'bloque_id' => $bloque->id, 'nombre' => 'Lucía', 'apellido' => 'Ríos',
            'dni' => '30.555.111', 'fecha_nacimiento' => '1995-04-10', 'telefono' => '1155550000', 'email' => 'lucia@test.local',
        ];
    }

    private function pendiente(array $extra = []): Preinscripcion
    {
        $datos = $this->datos($extra);

        return Preinscripcion::query()->create(array_merge($datos, ['estado' => 'pendiente', 'fecha_nacimiento' => $datos['fecha_nacimiento']]));
    }

    public function test_aprobar_crea_alumno_inscripto_y_avisa(): void
    {
        $admin = $this->admin();
        $pre = $this->pendiente();
        $bloque = $pre->bloque;

        $this->actingAs($admin)->post(route('preinscripciones.aprobar', $pre))->assertRedirect();

        $pre->refresh();
        $this->assertSame('aprobada', $pre->estado);
        $this->assertNotNull($pre->alumno_id);
        $alumno = Alumno::query()->findOrFail($pre->alumno_id);
        $this->assertSame('Lucía Ríos', $alumno->nombre_apellido);
        $this->assertTrue($alumno->bloques()->where('bloques.id', $bloque->id)->exists());
        $this->assertNotNull($alumno->persona_id);
    }

    public function test_aprobar_sin_cupo_no_inscribe(): void
    {
        $pre = $this->pendiente();
        $pre->bloque->update(['cantidad_max_alumnos' => 0]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PreinscripcionService::class)->aprobar($pre, $this->admin());
    }

    public function test_rechazar_guarda_el_motivo_y_no_crea_alumno(): void
    {
        $admin = $this->admin();
        $pre = $this->pendiente();

        $this->actingAs($admin)->post(route('preinscripciones.rechazar', $pre), ['motivo' => 'No hay cupo en ese horario'])->assertRedirect();

        $pre->refresh();
        $this->assertSame('rechazada', $pre->estado);
        $this->assertSame('No hay cupo en ese horario', $pre->motivo_rechazo);
        $this->assertSame(0, Alumno::query()->count());
    }

    public function test_una_preinscripcion_no_se_resuelve_dos_veces(): void
    {
        $admin = $this->admin();
        $pre = $this->pendiente();
        $this->actingAs($admin)->post(route('preinscripciones.rechazar', $pre), ['motivo' => 'x']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PreinscripcionService::class)->aprobar($pre->fresh(), $admin);
    }

    public function test_avisa_por_correo_si_la_persona_dejo_uno(): void
    {
        $pre = $this->pendiente();
        $this->assertSame('email', app(PreinscripcionService::class)->avisar($pre, 'hola'));
    }

    public function test_sin_permiso_no_se_aprueba_ni_se_ve_la_bandeja(): void
    {
        $pre = $this->pendiente();
        $alumno = $this->usuario('Sin permiso');

        $this->actingAs($alumno)->get(route('preinscripciones.index'))->assertForbidden();
        $this->actingAs($alumno)->post(route('preinscripciones.aprobar', $pre))->assertForbidden();
        $this->assertSame('pendiente', $pre->fresh()->estado);
    }

    public function test_la_api_lista_y_aprueba(): void
    {
        Sanctum::actingAs($this->admin());
        $pre = $this->pendiente();

        $this->getJson('/api/v1/preinscripciones')->assertOk()->assertJsonPath('data.0.nombre', 'Lucía');
        $this->postJson("/api/v1/preinscripciones/{$pre->id}/aprobar")->assertOk()->assertJsonPath('estado', 'aprobada');
        $this->getJson('/api/v1/preinscripciones?estado=aprobada')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_formulario_se_puede_incrustar_y_el_resto_de_la_app_no(): void
    {
        $this->datos();
        $this->get(route('inscripcion.create'))->assertOk()->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *; object-src \'none\'; base-uri \'self\'');
        $this->get(route('login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
