<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class AuditoriaApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_requiere_permiso_de_auditoria(): void
    {
        $user = $this->usuario('Tesorero');
        $this->asignarRol($user->persona, 'tesorero');
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/auditoria')->assertForbidden();
        $this->getJson('/api/v1/auditoria/catalogo')->assertForbidden();
    }

    public function test_las_operaciones_de_la_app_quedan_auditadas_con_origen_api_y_se_filtran(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/v1/personas', ['nombre' => 'Lucía', 'estado' => 'activo'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/personas/{$id}", ['nombre' => 'Lucía María', 'estado' => 'activo'])->assertOk();
        $this->persona('Otra');

        $r = $this->getJson("/api/v1/auditoria?entidad=Persona&entidad_id={$id}")->assertOk();
        $this->assertSame(['updated', 'created'], collect($r->json('data'))->pluck('accion')->all());
        $this->assertSame('api', $r->json('data.0.origen'));
        $this->assertSame($admin->id, $r->json('data.0.usuario.id'));
        $this->assertContains('nombre', $r->json('data.0.campos'));

        $detalle = $this->getJson('/api/v1/auditoria/'.$r->json('data.0.id'))->assertOk();
        $this->assertSame('Lucía', $detalle->json('data.antes.nombre'));
        $this->assertSame('Lucía María', $detalle->json('data.despues.nombre'));

        $this->assertContains('Persona', $this->getJson('/api/v1/auditoria/catalogo')->assertOk()->json('entidades'));
        $this->assertSame(0, $this->getJson('/api/v1/auditoria?accion=deleted')->assertOk()->json('meta.total'));
    }
}
