<?php

namespace Tests\Feature\Api;

use App\Models\Auditoria;
use App\Models\Beca;
use App\Models\Cuota;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class PersonasBecasApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_alta_de_persona_valida_y_normaliza_dni(): void
    {
        $user = $this->usuario('Secretaría');
        $this->asignarRol($user->persona, 'administrativo');
        Sanctum::actingAs($user->fresh());

        $this->postJson('/api/v1/personas', ['estado' => 'activo'])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $r = $this->postJson('/api/v1/personas', ['nombre' => 'Lucía', 'apellido' => 'Paz', 'dni' => '30.123.456', 'estado' => 'activo'])
            ->assertCreated()
            ->assertJsonPath('data.nombre_completo', 'Lucía Paz')
            ->assertJsonPath('data.dni', '30123456')
            ->assertJsonPath('data.acciones.editar', true);
        $this->assertNotNull(Auditoria::query()->where('entidad_tipo', 'Persona')->where('entidad_id', $r->json('data.id'))->where('accion', 'created')->first());

        $this->postJson('/api/v1/personas', ['nombre' => 'Otra', 'dni' => '30123456', 'estado' => 'activo'])
            ->assertStatus(422)->assertJsonValidationErrors('dni');
    }

    public function test_sin_permiso_no_crea_ni_edita_personas(): void
    {
        $sede = $this->sede('Palomar');
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $this->bloque($sede));
        $otra = $this->persona('Otra');
        Sanctum::actingAs($profe->fresh());

        $this->postJson('/api/v1/personas', ['nombre' => 'X', 'estado' => 'activo'])->assertForbidden();
        $this->putJson("/api/v1/personas/{$otra->id}", ['nombre' => 'X', 'estado' => 'activo'])->assertForbidden();
    }

    public function test_coordinador_no_edita_personas_de_otra_sede_cambiando_el_id(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        $propia = $this->persona('Propia');
        $this->inscribirAlumno($propia, $this->bloque($palomar));
        $ajena = $this->persona('Ajena');
        $this->inscribirAlumno($ajena, $this->bloque($varela));
        Sanctum::actingAs($coord->fresh());

        $this->putJson("/api/v1/personas/{$propia->id}", ['nombre' => 'Propia Editada', 'estado' => 'activo'])->assertOk()
            ->assertJsonPath('data.nombre', 'Propia Editada');
        $this->assertSame('Propia Editada', $propia->alumnos()->first()->nombre_apellido, 'la edición se propaga a la ficha de alumno');

        $this->getJson("/api/v1/personas/{$ajena->id}")->assertForbidden();
        $this->putJson("/api/v1/personas/{$ajena->id}", ['nombre' => 'Hack', 'estado' => 'activo'])->assertForbidden();
        $this->assertSame('Ajena', $ajena->fresh()->nombre);

        $ids = collect($this->getJson('/api/v1/personas')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($propia->id, $ids);
        $this->assertNotContains($ajena->id, $ids);
    }

    public function test_ficha_muestra_funciones_multiples_y_estado_de_cuenta_segun_permiso(): void
    {
        $sede = $this->sede('Palomar');
        $bloqueA = $this->bloque($sede, 'Bloque A');
        $bloqueB = $this->bloque($sede, 'Bloque B');
        $ana = $this->persona('Ana');
        $this->inscribirAlumno($ana, $bloqueA);
        $this->asignarDocente($ana, $bloqueB);
        Cuota::query()->create(['nombre' => 'Marzo', 'monto' => 20000, 'mes' => 3, 'año' => now()->year, 'alcance' => 'general', 'activo' => true]);

        $tesorero = $this->usuario('Tesorero');
        $this->asignarRol($tesorero->persona, 'tesorero');
        $this->asignarRol($tesorero->persona, 'administrativo');
        Sanctum::actingAs($tesorero->fresh());

        $r = $this->getJson("/api/v1/personas/{$ana->id}")->assertOk();
        $roles = collect($r->json('data.funciones'))->pluck('rol')->unique()->all();
        $this->assertContains('alumno', $roles);
        $this->assertContains('profesor', $roles);
        $this->assertSame('Bloque B', $r->json('data.profesor.bloques.0.nombre'));
        $this->assertSame(20000, (int) $r->json('data.alumnos.0.estado_cuenta.totales.saldo'));
        $this->assertTrue($r->json('data.acciones.gestionar_becas'));
        $this->assertFalse($r->json('data.acciones.inscribir_alumno'), 'ya es alumno');
    }

    public function test_fusion_requiere_permiso_global_y_unifica_fichas(): void
    {
        $sede = $this->sede('Palomar');
        $conservar = $this->persona('Ana', '111');
        $duplicada = $this->persona('Ana B');
        $alumno = $this->inscribirAlumno($duplicada, $this->bloque($sede));

        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $sede);
        Sanctum::actingAs($coord->fresh());
        $this->postJson("/api/v1/personas/{$conservar->id}/fusionar", ['duplicada_id' => $duplicada->id])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/personas/{$conservar->id}/fusionar", ['duplicada_id' => $conservar->id])->assertStatus(422);
        $this->postJson("/api/v1/personas/{$conservar->id}/fusionar", ['duplicada_id' => $duplicada->id])->assertOk();

        $this->assertSame($conservar->id, $alumno->fresh()->persona_id);
        $this->assertSame($conservar->id, Persona::withTrashed()->find($duplicada->id)->fusionada_en_id);
    }

    public function test_becas_se_otorgan_con_permiso_y_se_aplican_al_estado_de_cuenta(): void
    {
        $sede = $this->sede('Palomar');
        $ana = $this->persona('Ana');
        $alumno = $this->inscribirAlumno($ana, $this->bloque($sede));
        Cuota::query()->create(['nombre' => 'Marzo', 'monto' => 20000, 'mes' => 3, 'año' => now()->year, 'alcance' => 'general', 'activo' => true]);

        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());
        $datos = ['alumno_id' => $alumno->id, 'tipo' => 'porcentaje', 'porcentaje' => 50, 'fecha_inicio' => now()->startOfYear()->toDateString(), 'motivo' => 'Hermanos'];
        $this->postJson("/api/v1/personas/{$ana->id}/becas", $datos)->assertForbidden();
        $this->getJson('/api/v1/becas')->assertOk();

        $tesorero = $this->usuario('Tesorero');
        $this->asignarRol($tesorero->persona, 'tesorero');
        Sanctum::actingAs($tesorero->fresh());
        $this->postJson("/api/v1/personas/{$ana->id}/becas", ['alumno_id' => $alumno->id, 'tipo' => 'porcentaje', 'fecha_inicio' => now()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('porcentaje');

        $beca = $this->postJson("/api/v1/personas/{$ana->id}/becas", $datos)->assertCreated()
            ->assertJsonPath('data.etiqueta', 'Beca 50%')->assertJsonPath('data.estado', 'activa')->json('data');

        $this->assertSame(10000, (int) $this->getJson("/api/v1/alumnos/{$alumno->id}/estado-cuenta")->json('totales.saldo'));

        $this->putJson("/api/v1/becas/{$beca['id']}", ['estado' => 'suspendida'])->assertOk()->assertJsonPath('data.estado', 'suspendida');
        $this->assertSame(20000, (int) $this->getJson("/api/v1/alumnos/{$alumno->id}/estado-cuenta")->json('totales.saldo'));
    }

    public function test_beca_de_alumno_de_otra_persona_no_se_otorga_por_url(): void
    {
        $sede = $this->sede('Palomar');
        $ana = $this->persona('Ana');
        $otra = $this->persona('Otra');
        $alumnoOtra = $this->inscribirAlumno($otra, $this->bloque($sede));
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/personas/{$ana->id}/becas", ['alumno_id' => $alumnoOtra->id, 'tipo' => 'total', 'fecha_inicio' => now()->toDateString()])
            ->assertNotFound();
        $this->assertSame(0, Beca::query()->count());
    }

    public function test_listado_de_becas_respeta_el_alcance_de_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $propio = $this->inscribirAlumno($this->persona('Propia'), $this->bloque($palomar));
        $ajeno = $this->inscribirAlumno($this->persona('Ajena'), $this->bloque($varela));
        foreach ([$propio, $ajeno] as $a) {
            Beca::query()->create(['alumno_id' => $a->id, 'tipo' => 'total', 'fecha_inicio' => now()->toDateString(), 'estado' => 'activa']);
        }
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());

        $alumnos = collect($this->getJson('/api/v1/becas')->assertOk()->json('data'))->pluck('alumno.id')->all();
        $this->assertSame([$propio->id], $alumnos);
        $this->putJson('/api/v1/becas/'.Beca::query()->where('alumno_id', $propio->id)->value('id'), ['estado' => 'finalizada'])->assertForbidden();
    }
}
