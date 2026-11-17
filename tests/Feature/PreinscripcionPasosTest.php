<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Preinscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Preinscripción por pasos: cada paso avisa al equipo una vez, en orden, y deja la lista de espera. */
class PreinscripcionPasosTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function sedeYBloque(): array
    {
        $sede = $this->sede('Sede Pasos');

        return [$sede, $this->bloque($sede, 'Martes 19h')];
    }

    /** Recorre el paso 1 y devuelve la preinscripción y su token. */
    private function empezar(array $extra = []): Preinscripcion
    {
        [$sede, $bloque] = $this->sedeYBloque();
        $this->post(route('inscripcion.paso', 1), $extra + [
            'sede_id' => $sede->id, 'bloque_id' => $bloque->id, 'nombre' => 'Lucía', 'apellido' => 'Ríos',
        ])->assertRedirect();

        return Preinscripcion::query()->sole();
    }

    private function paso(Preinscripcion $pre, int $paso, array $datos)
    {
        return $this->post(route('inscripcion.paso', $paso), $datos + ['p' => $pre->token]);
    }

    private function avisosInternos(): int
    {
        return User::query()->get()->sum(fn (User $u) => $u->notifications()->count());
    }

    public function test_el_paso_1_crea_la_preinscripcion_en_curso_y_avisa_al_equipo(): void
    {
        $admin = $this->admin();
        $this->assertSame(0, $this->avisosInternos());

        [$sede, $bloque] = $this->sedeYBloque();
        $this->post(route('inscripcion.paso', 1), [
            'sede_id' => $sede->id, 'bloque_id' => $bloque->id, 'nombre' => 'Lucía', 'apellido' => 'Ríos',
        ])->assertRedirect();

        $pre = Preinscripcion::query()->sole();
        $this->assertSame(Preinscripcion::EN_CURSO, $pre->estado);
        $this->assertSame(1, $pre->paso);
        $this->assertNotEmpty($pre->token);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('Preinscripción en curso: Lucía Ríos', $admin->notifications()->first()->data['titulo']);
        $this->assertStringContainsString('Sede: Sede Pasos', $admin->notifications()->first()->data['mensaje']);
    }

    public function test_cada_paso_avisa_una_vez_y_el_ultimo_deja_la_lista_de_espera(): void
    {
        $admin = $this->admin();
        $pre = $this->empezar();

        $this->paso($pre, 2, ['dni' => '30.555.111', 'fecha_nacimiento' => '1995-04-10'])->assertRedirect();
        $this->paso($pre, 3, ['telefono' => '1155550000', 'email' => 'lucia@test.local'])->assertRedirect();
        $this->paso($pre, 4, ['notas' => 'Quiero tambores'])->assertRedirect();

        $pre->refresh();
        $this->assertSame(4, $pre->paso);
        $this->assertSame(Preinscripcion::PENDIENTE, $pre->estado);
        $this->assertSame('30555111', $pre->dni, 'el DNI se guarda sin puntos');
        $this->assertSame(0, Alumno::query()->count(), 'no crea el alumno hasta que administración apruebe');

        // Un aviso por paso: 4 en total, cada uno con su título.
        $titulos = $admin->notifications()->get()->pluck('data.titulo')->all();
        $this->assertCount(4, $titulos);
        $this->assertContains('Preinscripción completa: Lucía Ríos', $titulos);
        $this->assertContains('Preinscripción en curso: Lucía Ríos', $titulos);
        $this->assertContains('Preinscripción: contacto: Lucía Ríos', $titulos);
        $this->assertSame(4, DB::table('notificacion_envios')->where('canal', 'interna')->count());
        $this->assertSame(4, DB::table('notificacion_envios')->where('canal', 'mail')->count(), 'el correo sale una vez por paso');
    }

    public function test_repetir_un_paso_no_vuelve_a_avisar(): void
    {
        $admin = $this->admin();
        $pre = $this->empezar();
        $this->paso($pre, 2, ['fecha_nacimiento' => '1995-04-10'])->assertRedirect();
        $antes = $admin->notifications()->count();

        // Un reenvío del paso 2 ya no es válido: el formulario sigue en el paso 3 y no se avisa de nuevo.
        $this->paso($pre, 2, ['fecha_nacimiento' => '1995-04-10'])->assertRedirect();
        $this->assertSame($antes, $admin->notifications()->count());
        $this->assertSame(2, $pre->fresh()->paso);
    }

    public function test_no_se_puede_saltar_un_paso(): void
    {
        $admin = $this->admin();
        $pre = $this->empezar();

        $this->paso($pre, 3, ['telefono' => '1155550000'])->assertRedirectContains('p='.$pre->token);
        $this->assertSame(1, $pre->fresh()->paso);
        $this->assertSame(1, $admin->notifications()->count(), 'solo el aviso del paso 1');
    }

    public function test_un_menor_necesita_los_datos_del_tutor_en_el_paso_3(): void
    {
        $pre = $this->empezar();
        $this->paso($pre, 2, ['fecha_nacimiento' => now()->subYears(12)->toDateString()])->assertRedirect();

        $this->paso($pre, 3, ['telefono' => '1155550000'])->assertSessionHasErrors(['tutor_nombre', 'tutor_vinculo', 'tutor_telefono']);
        $this->assertSame(2, $pre->fresh()->paso);

        $this->paso($pre, 3, [
            'telefono' => '1155550000', 'tutor_nombre' => 'Marta Ríos', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1166660000',
        ])->assertRedirect();
        $this->assertSame(3, $pre->fresh()->paso);
    }

    public function test_el_trampa_para_robots_no_guarda_ni_avisa(): void
    {
        $admin = $this->admin();
        [$sede, $bloque] = $this->sedeYBloque();
        $this->post(route('inscripcion.paso', 1), [
            'sede_id' => $sede->id, 'bloque_id' => $bloque->id, 'nombre' => 'Bot', 'apellido' => 'Spam', 'sitio_web' => 'http://spam',
        ])->assertRedirect();

        $this->assertSame(0, Preinscripcion::query()->count());
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_un_token_inventado_no_sirve(): void
    {
        $this->post(route('inscripcion.paso', 2), ['p' => 'no-existe', 'fecha_nacimiento' => '1995-04-10'])->assertRedirect(route('inscripcion.create'));
        $this->assertSame(0, Preinscripcion::query()->count());
    }

    public function test_el_link_muestra_el_paso_que_sigue_y_la_confirmacion_al_final(): void
    {
        $pre = $this->empezar();
        $this->get(route('inscripcion.create', ['p' => $pre->token]))->assertOk()->assertSee('Paso 2 de 4');

        $this->paso($pre, 2, ['fecha_nacimiento' => '1995-04-10']);
        $this->paso($pre, 3, ['telefono' => '1155550000']);
        $this->paso($pre, 4, []);
        $this->get(route('inscripcion.create', ['p' => $pre->token]))->assertOk()->assertSee('Preinscripción recibida')
            ->assertSee('lista de espera');
    }

    public function test_la_bandeja_ve_la_preinscripcion_completa_y_se_puede_aprobar(): void
    {
        $admin = $this->admin();
        $pre = $this->empezar();
        $this->paso($pre, 2, ['fecha_nacimiento' => '1995-04-10']);
        $this->paso($pre, 3, ['telefono' => '1155550000']);
        $this->paso($pre, 4, []);

        $this->actingAs($admin)->get(route('preinscripciones.index'))->assertOk()->assertSee('Lucía');
        $this->actingAs($admin)->post(route('preinscripciones.aprobar', $pre))->assertRedirect();
        $this->assertSame('aprobada', $pre->fresh()->estado);
    }

    public function test_el_formulario_se_muestra_y_se_puede_incrustar(): void
    {
        $this->sedeYBloque();
        $this->get(route('inscripcion.create'))->assertOk()->assertSee('Quiero inscribirme')->assertSee('Paso 1 de 4')
            ->assertHeaderMissing('X-Frame-Options');
        $this->post(route('inscripcion.paso', 1), ['sede_id' => 1])->assertSessionHasErrors(['nombre', 'apellido']);
    }

    public function test_el_bloque_del_paso_1_tiene_que_ser_de_la_sede(): void
    {
        [$sede] = $this->sedeYBloque();
        $otro = $this->bloque($this->sede('Otra'), 'Ajeno');
        $this->post(route('inscripcion.paso', 1), [
            'sede_id' => $sede->id, 'bloque_id' => $otro->id, 'nombre' => 'Lucía', 'apellido' => 'Ríos',
        ])->assertSessionHasErrors('bloque_id');
        $this->assertSame(0, Preinscripcion::query()->count());
    }
}
