<?php

namespace Tests\Feature;

use App\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Alcance del tablero de dirección (permiso reportes.direccion):
 * dirección, coordinación y tesorería ven toda la escuela (config permisos.alcance_global);
 * el resto de los roles con el permiso ve solo sus sedes, en la web y en la API.
 */
class TableroDireccionAlcanceTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_coordinador_ve_toda_la_escuela_aunque_este_asignado_a_una_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $this->bloque($palomar, 'Bloque P');
        $this->bloque($varela, 'Bloque V');
        Gasto::query()->create(['sede_id' => $varela->id, 'fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 99999, 'estado' => 'aprobado']);
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);

        $this->actingAs($coord->fresh())->get('/direccion')->assertOk()->assertSee('Toda la escuela.');
        Sanctum::actingAs($coord->fresh());
        $this->getJson('/api/v1/direccion')->assertOk()->assertJsonPath('alcance', 'global');
        $this->getJson('/api/v1/direccion?sede_id='.$varela->id)->assertOk();
    }

    public function test_tesorero_ve_toda_la_escuela(): void
    {
        $this->sede('Palomar');
        $this->sede('Varela');
        $tesorero = $this->usuario('Tesorero');
        $this->asignarRol($tesorero->persona, 'tesorero', 'sede', $this->sede('Centro'));

        $this->actingAs($tesorero->fresh())->get('/direccion')->assertOk()->assertSee('Toda la escuela.');
        Sanctum::actingAs($tesorero->fresh());
        $this->getJson('/api/v1/direccion')->assertOk()->assertJsonPath('alcance', 'global');
    }

    public function test_otro_rol_con_el_permiso_sigue_viendo_solo_sus_sedes(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $this->bloque($palomar, 'Bloque P');
        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador', 'sede', $palomar);

        $this->actingAs($contador->fresh())->get('/direccion')->assertOk()
            ->assertSee('Solo Palomar.')->assertDontSee('Varela');
        Sanctum::actingAs($contador->fresh());
        $this->getJson('/api/v1/direccion')->assertOk()
            ->assertJsonPath('alcance', 'sedes')
            ->assertJsonCount(1, 'sedes');
        $this->getJson('/api/v1/direccion?sede_id='.$varela->id)->assertForbidden();
    }

    public function test_direccion_ve_toda_la_escuela_y_otros_roles_no_entran(): void
    {
        $this->sede('Palomar');
        $dir = $this->admin();
        $this->actingAs($dir)->get('/direccion')->assertOk()->assertSee('Toda la escuela.');
        Sanctum::actingAs($dir);
        $this->getJson('/api/v1/direccion')->assertOk()->assertJsonPath('alcance', 'global');

        $profe = $this->usuario('Profe');
        $this->asignarRol($profe->persona, 'profesor', 'sede', $this->sede('Varela'));
        $this->actingAs($profe->fresh())->get('/direccion')->assertForbidden();
        Sanctum::actingAs($profe->fresh());
        $this->getJson('/api/v1/direccion')->assertForbidden();
    }
}
