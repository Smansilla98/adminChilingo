<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\ProgramaRitmo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ProgramaGestionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function toque(string $nombre, int $año = 1, int $orden = 1, array $extra = []): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create(array_merge([
            'slug' => \Illuminate\Support\Str::slug($año.'-'.$nombre),
            'año' => $año,
            'orden' => $orden,
            'nombre' => $nombre,
            'publicado' => true,
        ], $extra));
    }

    /** @param  array<string, mixed>  $cambios */
    private function guardar(ProgramaRitmo $t, array $cambios = [])
    {
        return $this->put(route('programa.gestion.update', $t->id), array_merge([
            'nombre' => $t->nombre,
            'año' => $t->año,
            'vigente' => $t->sigueVigente() ? '1' : '0',
            'en_programa' => $t->estaEnPrograma() ? '1' : '0',
            'estado_nota' => $t->estado_nota,
        ], $cambios));
    }

    public function test_solo_administracion_entra_a_la_gestion(): void
    {
        $t = $this->toque('Samba');

        $this->get(route('programa.gestion'))->assertRedirect(route('login'));

        $persona = $this->persona('Profe');
        $profe = $this->usuario('Profe', $persona);
        $this->asignarPermiso($persona, 'partituras.admin');
        $this->actingAs($profe)->get(route('programa.gestion'))->assertForbidden();
        $this->actingAs($profe)->put(route('programa.gestion.update', $t->id), ['nombre' => 'X', 'año' => 2])->assertForbidden();
        $this->assertSame('Samba', $t->fresh()->nombre);

        $this->actingAs($this->admin())->get(route('programa.gestion'))
            ->assertOk()
            ->assertSee('Gestión del programa')
            ->assertSee('value="Samba"', false);
    }

    public function test_renombrar_guarda_el_nombre_anterior_y_mantiene_el_enlace(): void
    {
        $t = $this->toque('Murga vieja');
        $this->actingAs($this->admin());

        $this->guardar($t, ['nombre' => '  Murga   nueva '])->assertRedirect();

        $t->refresh();
        $this->assertSame('Murga nueva', $t->nombre);
        $this->assertSame('1-murga-vieja', $t->slug);
        $this->assertSame(['Murga vieja'], array_column($t->historialNombres(), 'nombre'));
        $this->get(route('programa.toque.show', $t))->assertOk()->assertSee('Antes: Murga vieja');

        $auditoria = Auditoria::query()->where('accion', 'programa.toque.gestion')->sole();
        $this->assertSame(['nombre' => 'Murga vieja'], $auditoria->datos_anteriores);

        // Volver al nombre viejo lo saca del historial (no queda repetido).
        $this->guardar($t, ['nombre' => 'Murga vieja']);
        $this->assertSame(['Murga nueva'], array_column($t->fresh()->historialNombres(), 'nombre'));

        // Corregir mayúsculas no es renombrar.
        $this->guardar($t->fresh(), ['nombre' => 'MURGA VIEJA']);
        $this->assertSame(['Murga nueva'], array_column($t->fresh()->historialNombres(), 'nombre'));
    }

    public function test_cambiar_de_año_lo_pasa_al_final_del_nuevo_año(): void
    {
        $t = $this->toque('Candombe', 1, 4);
        $this->toque('Zamba', 3, 40);
        $this->actingAs($this->admin());

        $this->guardar($t, ['año' => 3]);

        $t->refresh();
        $this->assertSame(3, $t->año);
        $this->assertSame(41, $t->orden);
    }

    public function test_un_toque_que_ya_no_se_toca_sigue_en_el_programa_marcado(): void
    {
        $t = $this->toque('Cumbia');
        $this->actingAs($this->admin());

        $this->guardar($t, ['vigente' => '0', 'estado_nota' => 'Se dejó de tocar en 2024.']);
        $t->refresh();
        $this->assertFalse($t->sigueVigente());
        $this->assertTrue($t->estaEnPrograma());

        auth()->logout();
        $this->get(route('programa.index'))->assertOk()->assertSeeInOrder(['Cumbia', 'Ya no se toca']);
        $this->get(route('programa.toque.show', $t))->assertOk()
            ->assertSee('Este toque ya no se toca en la escuela.')
            ->assertSee('Se dejó de tocar en 2024.');
    }

    public function test_un_toque_fuera_del_programa_no_aparece_en_lo_publico_ni_en_la_app(): void
    {
        $sigue = $this->toque('Samba', 1, 1);
        $retirado = $this->toque('Toque retirado', 1, 2);
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->guardar($retirado, ['en_programa' => '0']);
        $this->assertFalse($retirado->fresh()->estaEnPrograma());

        // La gestión lo sigue mostrando (para poder volver a sumarlo).
        $this->get(route('programa.gestion', ['estado' => 'retirados']))
            ->assertOk()->assertSee('value="Toque retirado"', false)->assertDontSee('value="Samba"', false);

        auth()->logout();
        $this->get(route('programa.index'))->assertOk()->assertSee('Samba')->assertDontSee('Toque retirado');
        $this->get(route('programa.partituras.index'))->assertOk()->assertDontSee('Toque retirado');
        $this->get(route('programa.toque.show', $retirado))->assertOk()
            ->assertSee('Este toque ya no forma parte del programa.');

        $persona = $this->persona('Ana');
        $alumna = $this->usuario('Ana', $persona);
        $this->asignarRol($persona, 'alumno');
        Sanctum::actingAs($alumna);
        $slugs = collect($this->getJson('/api/v1/partituras')->assertOk()->json('data'))->pluck('slug');
        $this->assertContains($sigue->slug, $slugs);
        $this->assertNotContains($retirado->slug, $slugs);
    }

    public function test_se_encuentra_por_su_nombre_anterior(): void
    {
        $t = $this->toque('Chamamé', 2);
        $this->actingAs($this->admin());
        $this->guardar($t, ['nombre' => 'Chamamé del litoral']);

        $this->get(route('programa.partituras.index', ['q' => 'chamamé']))->assertOk()->assertSee('Chamamé del litoral');
        $this->get(route('programa.gestion', ['q' => 'chamamé']))->assertOk()->assertSee('value="Chamamé del litoral"', false);

        // El importador del cuadernillo lo reconoce por el nombre viejo (no lo duplica).
        $this->assertSame($t->id, ProgramaRitmo::porNombre('Chamamé', 2)?->id);
        $this->assertNull(ProgramaRitmo::porNombre('Otro toque'));
    }

    public function test_valida_nombre_y_año(): void
    {
        $t = $this->toque('Samba');
        $this->actingAs($this->admin());

        $this->guardar($t, ['nombre' => '', 'año' => 9])->assertSessionHasErrors(['nombre', 'año']);
        $this->assertSame('Samba', $t->fresh()->nombre);
    }

    public function test_el_programa_muestra_los_toques_de_septimo_año(): void
    {
        $this->toque('Toque de séptimo', 7);

        $this->get(route('programa.index'))->assertOk()->assertSee('Toque de séptimo');
    }
}
