<?php

namespace Tests\Feature;

use App\Models\Bloque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * El año del bloque es una referencia; «funciona desde» permite ver cuántos años lleva
 * y qué año del programa le correspondería por antigüedad.
 */
class BloqueAntiguedadTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_se_guarda_desde_cuando_funciona_y_se_muestra_la_antiguedad(): void
    {
        $sede = $this->sede('Palomar');
        $desde = (int) now()->year - 17;

        $this->actingAs($this->admin())->post(route('bloques.store'), [
            'nombre' => 'Bloque histórico', 'año' => 3, 'funciona_desde' => $desde, 'sede_id' => $sede->id, 'cantidad_max_alumnos' => 30, 'activo' => 1,
        ])->assertSessionHasNoErrors();

        $bloque = Bloque::query()->where('nombre', 'Bloque histórico')->firstOrFail();
        $this->assertSame($desde, $bloque->funciona_desde);
        $this->assertSame(17, $bloque->aniosFuncionando());
        $this->assertSame(6, $bloque->anioSugeridoPorAntiguedad());

        $this->get(route('bloques.index'))->assertOk()->assertSee('desde '.$desde)->assertSee('por antigüedad: 6° o más');
        $this->get(route('bloques.show', $bloque))->assertOk()->assertSee('desde '.$desde.' · 17 años');
    }

    public function test_es_opcional_y_no_acepta_anios_imposibles(): void
    {
        $sede = $this->sede('Quilmes');
        $base = ['nombre' => 'Nuevo', 'año' => 1, 'sede_id' => $sede->id, 'cantidad_max_alumnos' => 20];
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('bloques.store'), $base)->assertSessionHasNoErrors();
        $this->assertNull(Bloque::query()->where('nombre', 'Nuevo')->value('funciona_desde'));

        $this->actingAs($admin)->post(route('bloques.store'), $base + ['funciona_desde' => 1980])->assertSessionHasErrors('funciona_desde');
        $this->actingAs($admin)->post(route('bloques.store'), $base + ['funciona_desde' => (int) now()->year + 1])->assertSessionHasErrors('funciona_desde');
    }

    public function test_un_bloque_nuevo_sugiere_primer_anio(): void
    {
        $bloque = $this->bloque($this->sede('Varela'));
        $bloque->update(['funciona_desde' => (int) now()->year]);

        $this->assertSame(0, $bloque->aniosFuncionando());
        $this->assertSame(1, $bloque->anioSugeridoPorAntiguedad());
        $this->assertStringContainsString('primer año', (string) $bloque->antiguedadTexto());
    }
}
