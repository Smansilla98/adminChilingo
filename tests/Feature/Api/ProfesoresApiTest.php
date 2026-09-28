<?php

namespace Tests\Feature\Api;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ProfesoresApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_alta_desde_persona_con_bloques_y_roles_de_sede(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede, 'Bloque A');
        $persona = $this->persona('Juana');
        Sanctum::actingAs($this->admin());

        $r = $this->postJson('/api/v1/profesores', [
            'persona_id' => $persona->id,
            'nombre' => 'Juana',
            'cuenta_modo' => 'ninguna',
            'bloques' => [['bloque_id' => $bloque->id, 'rol' => 'titular']],
            'sedes' => [['sede_id' => $sede->id, 'rol' => 'coordinador']],
        ])->assertCreated();

        $this->assertSame($persona->id, $r->json('data.persona_id'));
        $this->assertSame('titular', $r->json('data.bloques.0.rol'));
        $this->assertSame('coordinador', $r->json('data.sedes.0.rol'));

        $this->postJson('/api/v1/profesores', ['persona_id' => $persona->id, 'nombre' => 'Juana', 'cuenta_modo' => 'ninguna'])
            ->assertStatus(422)->assertJsonValidationErrors('persona_id');
    }

    public function test_alta_con_cuenta_nueva_valida_contrasena_y_crea_usuario(): void
    {
        Sanctum::actingAs($this->admin());
        $base = ['nombre' => 'Pedro', 'cuenta_modo' => 'nueva', 'email' => 'pedro@test.local', 'login_username' => 'pedro'];

        $this->postJson('/api/v1/profesores', $base + ['login_password' => 'corta', 'login_password_confirmation' => 'corta'])
            ->assertStatus(422)->assertJsonValidationErrors('login_password');

        $id = $this->postJson('/api/v1/profesores', $base + ['login_password' => 'clave-segura-1', 'login_password_confirmation' => 'clave-segura-1'])
            ->assertCreated()->assertJsonPath('data.cuenta.username', 'pedro')->json('data.id');
        $user = User::query()->where('username', 'pedro')->firstOrFail();
        $this->assertSame($user->id, Profesor::query()->find($id)->user_id);
        $this->assertNotNull($user->persona_id, 'la cuenta queda vinculada a la persona del docente');
    }

    public function test_listado_y_ficha_respetan_el_alcance_de_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $propio = $this->asignarDocente($this->persona('Propio'), $this->bloque($palomar));
        $ajeno = $this->asignarDocente($this->persona('Ajeno'), $this->bloque($varela));
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());

        $ids = collect($this->getJson('/api/v1/profesores')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$propio->id], $ids);
        $this->getJson("/api/v1/profesores/{$propio->id}")->assertOk()->assertJsonPath('data.acciones.editar', false);
        $this->getJson("/api/v1/profesores/{$ajeno->id}")->assertForbidden();
        $this->putJson("/api/v1/profesores/{$propio->id}", ['nombre' => 'X', 'cuenta_modo' => 'ninguna'])->assertForbidden();
    }

    public function test_administrador_de_sede_no_edita_docentes_de_otra_sede_por_id(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $ajeno = $this->asignarDocente($this->persona('Ajeno'), $this->bloque($varela));
        $admin = $this->usuario('Admin sede');
        $this->asignarRol($admin->persona, 'administrador', 'sede', $palomar);
        Sanctum::actingAs($admin->fresh());

        $this->putJson("/api/v1/profesores/{$ajeno->id}", ['nombre' => 'Hack', 'cuenta_modo' => 'ninguna'])->assertForbidden();
        $this->deleteJson("/api/v1/profesores/{$ajeno->id}")->assertForbidden();
        $this->assertSame('Ajeno', $ajeno->fresh()->nombre);

        // La web aplica la misma Policy.
        $this->actingAs($admin->fresh())->get("/profesores/{$ajeno->id}/edit")->assertForbidden();
    }

    public function test_edicion_parcial_conserva_bloques_y_eliminacion_segura(): void
    {
        $sede = $this->sede('Palomar');
        $profesor = $this->asignarDocente($this->persona('Juana'), $this->bloque($sede));
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/profesores/{$profesor->id}", ['nombre' => 'Juana María', 'telefono' => '1122', 'cuenta_modo' => 'ninguna'])
            ->assertOk()->assertJsonPath('data.nombre', 'Juana María')->assertJsonCount(1, 'data.bloques');

        $this->deleteJson("/api/v1/profesores/{$profesor->id}")->assertStatus(422);
        $this->putJson("/api/v1/profesores/{$profesor->id}", ['nombre' => 'Juana María', 'cuenta_modo' => 'ninguna', 'bloques' => []])->assertOk();
        $this->deleteJson("/api/v1/profesores/{$profesor->id}")->assertOk();
        $this->assertNull(Profesor::query()->find($profesor->id));
    }

    public function test_sin_permiso_de_alta_es_403(): void
    {
        $user = $this->usuario('Adm');
        $this->asignarRol($user->persona, 'administrativo');
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/profesores')->assertOk();
        $this->postJson('/api/v1/profesores', ['nombre' => 'X', 'cuenta_modo' => 'ninguna'])->assertForbidden();
        $this->getJson('/api/v1/profesores/catalogo')->assertForbidden();
    }
}
