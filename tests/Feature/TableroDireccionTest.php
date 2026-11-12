<?php

namespace Tests\Feature;

use App\Domain\Reportes\TableroDireccionService;
use App\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

class TableroDireccionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_tablero_por_sede_global_excel_y_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $bloque = $this->bloque($palomar, 'Bloque P');
        $this->bloque($varela, 'Bloque V');
        $this->inscribirAlumno($this->persona('Ana'), $bloque);
        Gasto::query()->create(['sede_id' => $palomar->id, 'fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 1000, 'estado' => 'aprobado']);

        $dir = $this->usuario('Dirección');
        $this->asignarPermiso($dir->persona, 'reportes.direccion');
        $this->actingAs($dir->fresh())->get('/direccion')->assertOk()->assertSee('Tablero de dirección')->assertSee('Ocupación');

        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        $this->actingAs($coord->fresh())->get('/direccion')->assertOk()->assertSee('Palomar')->assertDontSee('Varela');
        $this->actingAs($coord->fresh())->get('/direccion?sede_id='.$varela->id)->assertForbidden();

        $this->actingAs($dir->fresh())->get('/direccion/excel')->assertOk();
        $this->actingAs($dir->fresh())->get('/direccion/imprimible')->assertOk()->assertSee('Mes a mes');
    }

    public function test_servicio_calcula_activos_ocupacion_y_proyeccion(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'), 'B', 1);
        $bloque->update(['cantidad_max_alumnos' => 4]);
        $this->inscribirAlumno($this->persona('A1'), $bloque);
        $this->inscribirAlumno($this->persona('A2'), $bloque);
        $baja = $this->inscribirAlumno($this->persona('Baja'), $bloque);
        $baja->update(['activo' => false]);

        $dir = $this->usuario('Dir');
        $this->asignarPermiso($dir->persona, 'reportes.direccion');
        $d = app(TableroDireccionService::class)->datos($dir->fresh());

        $this->assertCount(12, $d['serie']);
        $this->assertSame(2, $d['alumnos']['activos']);
        $this->assertSame(50.0, $d['ocupacion']['porcentaje']);
        $this->assertSame(2, $d['ocupacion']['inscriptos']);
        $this->assertNotNull($d['proyeccion']['etiqueta']);
    }
}
