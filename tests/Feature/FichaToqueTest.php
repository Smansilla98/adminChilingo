<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\FichaToque;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Ficha del toque: la redacta la escuela y se muestra en la web y en la app. */
class FichaToqueTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function toque(): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-ficha', 'año' => 1, 'orden' => 97, 'nombre' => 'Ficha', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('Ficha')],
        ]);
    }

    public function test_normaliza_compas_tempo_e_instrumentos(): void
    {
        $f = FichaToque::normalizar([
            'origen' => '  Ensayo de Ciudad Jardín  ',
            'compas' => '7/5',
            'tempo_sugerido' => 999,
            'instrumentos' => ['surdo_grave', 'todos', 'guitarra', 'surdo_grave', 'timbal'],
            'nota' => 'Entrada en el tiempo 3.',
        ]);
        $this->assertSame('Ensayo de Ciudad Jardín', $f['origen']);
        $this->assertNull($f['compas'], 'compás inválido se descarta');
        $this->assertSame(260, $f['tempo_sugerido']);
        $this->assertSame(['surdo_grave', 'timbal'], $f['instrumentos']);
        $this->assertSame('Entrada en el tiempo 3.', $f['nota']);

        $this->assertSame('3/4', FichaToque::normalizar(['compas' => '3/4'])['compas']);
        $this->assertNull(FichaToque::normalizar(['origen' => '  ', 'compas' => '']), 'ficha vacía no se guarda');
        $this->assertNull(FichaToque::normalizar('texto suelto'));
    }

    public function test_admin_guarda_la_ficha_desde_la_web_y_se_ve_en_la_ficha(): void
    {
        $toque = $this->toque();
        $this->actingAs($this->admin());

        $this->post(route('programa.toque.ficha', $toque), [
            'origen' => 'Taller de Varela', 'compas' => '4/4', 'tempo_sugerido' => 96,
            'instrumentos' => ['surdo_grave', 'repique'], 'nota' => 'Cuidado con el cambio.',
        ])->assertRedirect()->assertSessionHas('status');

        $ficha = $toque->fresh()->mediosNormalizados()['ficha'];
        $this->assertSame('Taller de Varela', $ficha['origen']);
        $this->assertSame(96, $ficha['tempo_sugerido']);

        $this->get(route('programa.toque.show', $toque))->assertOk()
            ->assertSee('Taller de Varela')->assertSee('96 bpm')->assertSee('Cuidado con el cambio.');
    }

    public function test_quien_no_administra_no_puede_guardar_la_ficha(): void
    {
        $toque = $this->toque();
        $this->actingAs($this->usuario('Visitante'));

        $this->post(route('programa.toque.ficha', $toque), ['origen' => 'Intruso'])->assertForbidden();
        $this->assertNull($toque->fresh()->mediosNormalizados()['ficha']);
    }

    public function test_api_guarda_la_ficha_y_la_muestra_al_leer_el_toque(): void
    {
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/v1/partituras/zz-ficha/ficha', ['origen' => 'Clase de los martes', 'tempo_sugerido' => 88, 'instrumentos' => ['timbal']])
            ->assertOk()->assertJsonPath('ficha.tempo_sugerido', 88);
        $this->getJson('/api/v1/partituras/zz-ficha')->assertOk()
            ->assertJsonPath('ficha.origen', 'Clase de los martes')
            ->assertJsonPath('ficha.instrumentos.0', 'timbal');
    }

    public function test_guardar_la_partitura_no_borra_la_ficha(): void
    {
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/partituras/zz-ficha/ficha', ['origen' => 'Original', 'compas' => '4/4'])->assertOk();

        $this->putJson('/api/v1/partituras/zz-ficha/score', ['score' => PartituraScore::vacia('Ficha')])->assertOk();

        $this->assertSame('Original', $toque->fresh()->mediosNormalizados()['ficha']['origen']);
    }
}
