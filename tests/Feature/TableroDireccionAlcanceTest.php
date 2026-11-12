<?php

namespace Tests\Feature;

use App\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Alcance del tablero de dirección (permiso reportes.direccion):
 * dirección ve toda la escuela; un coordinador ve solo las sedes donde tiene el rol, en la web y en la API.
 */
class TableroDireccionAlcanceTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_coordinador_de_una_sede_ve_solo_esa_sede_y_lo_dice(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $this->bloque($palomar, 'Bloque P');
        $this->bloque($varela, 'Bloque V');
        Gasto::query()->create(['sede_id' => $varela->id, 'fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 99999, 'estado' => 'aprobado']);
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);

        $this->actingAs($coord->fresh())->get('/direccion')->assertOk()
            ->assertSee('Solo Palomar.')->assertDontSee('Varela');
        Sanctum::actingAs($coord->fresh());
        $this->getJson('/api/v1/direccion')->assertOk()
            ->assertJsonPath('alcance', 'sedes')
            ->assertJsonCount(1, 'sedes')
            ->assertJsonPath('sedes.0.nombre', 'Palomar');
        $this->getJson('/api/v1/direccion?sede_id='.$varela->id)->assertForbidden();
    }

    public function test_coordinador_de_dos_sedes_ve_las_dos_y_nada_de_una_tercera(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $tercera = $this->sede('Centro');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $varela);

        $this->actingAs($coord->fresh())->get('/direccion')->assertOk()
            ->assertSee('Solo Palomar, Varela.')->assertDontSee('Centro');
        $this->actingAs($coord->fresh())->get('/direccion?sede_id='.$tercera->id)->assertForbidden();
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
