<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class UsuariosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_crear_cuenta_para_persona_existente_con_rol_inicial(): void
    {
        $sede = $this->sede('Palomar');
        $persona = $this->persona('Lucía');
        Sanctum::actingAs($this->admin());

        $datos = ['persona_id' => $persona->id, 'username' => 'lucia', 'email' => 'lucia@test.local', 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1', 'rol' => 'coordinador', 'ambito' => 'sede', 'sede_id' => $sede->id];
        $r = $this->postJson('/api/v1/usuarios', $datos)->assertCreated()->assertJsonPath('data.persona.id', $persona->id);
        $this->assertContains('coordinador', collect($r->json('data.funciones'))->pluck('rol')->all());

        $this->postJson('/api/v1/usuarios', ['username' => 'otra', 'email' => 'otra@test.local'] + $datos)->assertStatus(422)->assertJsonValidationErrors('persona_id');
        $this->postJson('/api/v1/usuarios', ['persona_id' => null, 'nombre' => 'Nueva'] + $datos)->assertStatus(422)->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_desactivar_y_resetear_cierran_las_sesiones_de_la_app(): void
    {
        $admin = $this->admin();
        $objetivo = $this->usuario('Ana');
        $objetivo->createToken('android app');
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/usuarios/{$objetivo->id}")->assertOk()->assertJsonPath('data.dispositivos.0.nombre', 'android app');

        $this->postJson("/api/v1/usuarios/{$objetivo->id}/resetear-acceso", ['password' => 'nueva-clave-1', 'password_confirmation' => 'otra'])->assertStatus(422);
        $this->postJson("/api/v1/usuarios/{$objetivo->id}/resetear-acceso", ['password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])->assertOk();
        $this->assertTrue(Hash::check('nueva-clave-1', $objetivo->fresh()->password));
        $this->assertSame(0, $objetivo->tokens()->count());

        $objetivo->createToken('ios app');
        $this->postJson("/api/v1/usuarios/{$objetivo->id}/estado", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertSame(0, $objetivo->tokens()->count());
        $this->postJson("/api/v1/usuarios/{$admin->id}/estado", ['activo' => false])->assertStatus(422);

        $this->putJson("/api/v1/usuarios/{$objetivo->id}", ['username' => 'ana-p', 'email' => 'ana@test.local', 'telefono' => '11'])->assertOk()->assertJsonPath('data.username', 'ana-p');
    }

    public function test_sin_alcance_global_no_administra_cuentas_y_no_toca_administradores(): void
    {
        $sede = $this->sede('Palomar');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $sede);
        Sanctum::actingAs($coord->fresh());
        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->getJson("/api/v1/usuarios/{$coord->id}")->assertOk(); // la propia cuenta sí

        // Quien gestiona usuarios pero no puede asignar administración no toca administradores.
        $gestor = $this->usuario('Gestor');
        foreach (['usuarios.view', 'usuarios.update', 'usuarios.permissions'] as $p) {
            $this->asignarPermiso($gestor->persona, $p);
        }
        $otroAdmin = $this->admin('Otro admin');
        Sanctum::actingAs($gestor->fresh());
        $this->getJson("/api/v1/usuarios/{$otroAdmin->id}")->assertOk()->assertJsonPath('data.acciones.editar', false)->assertJsonPath('data.acciones.gestionar_permisos', false);
        $this->postJson("/api/v1/usuarios/{$otroAdmin->id}/estado", ['activo' => false])->assertForbidden();
        $this->postJson("/api/v1/usuarios/{$otroAdmin->id}/asignaciones", ['tipo' => 'rol', 'nombre' => 'contador', 'ambito' => 'global'])->assertForbidden();
        $this->assertTrue((bool) User::query()->find($otroAdmin->id)->activo);
        $this->assertArrayNotHasKey('administrador', $this->getJson('/api/v1/usuarios/catalogo')->assertOk()->json('roles'));
    }
}
