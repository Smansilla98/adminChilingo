<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\ProgramaRitmo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class AlumnoYAccesibilidadTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Alumno de 2° año con DNI y fecha de nacimiento en su ficha (sin cuenta). */
    private function alumnoDeSegundo(string $dni = '30123456'): Alumno
    {
        $sede = $this->sede('Saavedra');
        $bloque = $this->bloque($sede, 'Bloque sábado', 2);
        $alumno = $this->inscribirAlumno($this->persona('Ana Gómez', $dni), $bloque);
        $alumno->forceFill(['dni' => $dni, 'fecha_nacimiento' => '2001-05-20'])->save();

        return $alumno->fresh();
    }

    private function toque(string $nombre, int $año): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => \Illuminate\Support\Str::slug($año.'-'.$nombre), 'año' => $año, 'orden' => 90,
            'nombre' => $nombre, 'publicado' => true,
        ]);
    }

    // ---- Profesor con poca práctica ----

    public function test_los_mensajes_de_validacion_estan_en_castellano_claro(): void
    {
        $errores = Validator::make(
            ['email' => 'x', 'nombre_apellido' => '', 'monto' => 'abc'],
            ['email' => 'email', 'nombre_apellido' => 'required', 'monto' => 'numeric'],
        )->errors()->all();

        $this->assertSame([
            'Revisá el correo: tiene que ser un correo como nombre@ejemplo.com.',
            'Falta completar el nombre y apellido.',
            'El monto tiene que ser un número.',
        ], $errores);
        $this->assertStringNotContainsString('validation.', implode(' ', $errores));
    }

    public function test_el_profesor_ve_todo_su_menu_y_entra_directo_a_su_unico_bloque(): void
    {
        $sede = $this->sede('Varela');
        $bloque = $this->bloque($sede, 'Bloque martes', 1);
        $persona = $this->persona('Marta Profe');
        $profe = $this->usuario('Marta Profe', $persona);
        $this->asignarDocente($persona, $bloque);
        $this->inscribirAlumno($this->persona('Luis Pérez'), $bloque);
        $this->actingAs($profe);

        $html = $this->get(route('dashboard'))->assertOk()
            ->assertSee('Asistencia de hoy')
            ->assertDontSee('chips grandes')
            ->getContent();
        preg_match_all('/class="nav-group( [^"]*)?"/', $html, $grupos);
        $this->assertNotEmpty($grupos[0]);
        foreach ($grupos[1] as $clases) {
            $this->assertStringContainsString('open', $clases, 'Con menú corto, todos los grupos están abiertos');
        }

        $this->get(route('profesor.asistencias.create'))
            ->assertRedirectContains('bloque_id='.$bloque->id);
        $this->get(route('profesor.asistencias.create', ['bloque_id' => $bloque->id]))->assertOk()
            ->assertSee('Faltó con aviso')->assertSee('Faltó sin aviso');
    }

    // ---- Registro del alumno ----

    public function test_el_alumno_crea_su_cuenta_con_dni_y_fecha_de_nacimiento(): void
    {
        $alumno = $this->alumnoDeSegundo();

        $this->post(route('registro.alumno.store'), [
            'dni' => '30.123.456', 'fecha_nacimiento' => '2001-05-20',
            'password' => 'tambor1', 'password_confirmation' => 'tambor1',
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $user = User::query()->where('username', '30123456')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame((int) $alumno->persona_id, (int) $user->persona_id);
        $this->assertSame($user->id, (int) $alumno->fresh()->user_id);
        $this->assertTrue($user->fresh()->isAlumno());
        $this->assertSame([], $user->fresh()->getRoleNames()->all(), 'no recibe roles: es alumno por estar inscripto');
        $this->assertStringEndsWith('.invalid', $user->email);

        // Entra con el DNI con puntos.
        auth()->logout();
        $this->post('/login', ['username' => '30.123.456', 'password' => 'tambor1'])->assertRedirect(route('dashboard'));
    }

    public function test_el_registro_no_deja_pasar_datos_que_no_coinciden_ni_cuentas_dobles(): void
    {
        $this->alumnoDeSegundo();
        $datos = ['dni' => '30123456', 'fecha_nacimiento' => '1999-01-01', 'password' => 'tambor1', 'password_confirmation' => 'tambor1'];

        $this->post(route('registro.alumno.store'), $datos)->assertSessionHasErrors('dni');
        $this->post(route('registro.alumno.store'), ['dni' => '99999999'] + $datos)->assertSessionHasErrors('dni');
        $this->assertGuest();

        $this->post(route('registro.alumno.store'), ['fecha_nacimiento' => '2001-05-20'] + $datos)->assertRedirect(route('dashboard'));
        auth()->logout();
        $this->post(route('registro.alumno.store'), ['fecha_nacimiento' => '2001-05-20'] + $datos)
            ->assertSessionHasErrors(['dni' => 'Ya tenés una cuenta. Entrá con tu DNI y tu contraseña. Si no te acordás la contraseña, pedile a tu profe o a administración que te la cambie.']);
        $this->assertSame(1, User::query()->where('username', 'like', '30123456%')->count());
    }

    public function test_el_registro_viejo_ya_no_crea_profesores(): void
    {
        $this->get('/register')->assertRedirect(route('registro.alumno'));
        $this->post('/register', ['name' => 'X', 'email' => 'x@x.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])->assertNotFound();
        $this->get(route('login'))->assertSee('Creala con tu DNI');
    }

    // ---- Material hasta el año que cursa ----

    public function test_sin_cuenta_el_programa_es_publico_pero_el_material_pide_entrar(): void
    {
        $toque = $this->toque('Candombe de prueba', 1);

        $this->get(route('programa.index'))->assertOk()->assertSee('Candombe de prueba')->assertSee('entrá con tu cuenta');
        $this->get(route('programa.toque.show', $toque))->assertRedirect(route('login'));
        $this->get(route('programa.toque.archivo', $toque))->assertRedirect(route('login'));
        $this->get(route('programa.partituras.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee('Para ver las partituras entrá con tu cuenta');
    }

    public function test_el_alumno_ve_el_material_hasta_su_año(): void
    {
        $alumno = $this->alumnoDeSegundo();
        $user = $this->usuario('Ana Gómez', $alumno->persona);
        $deSegundo = $this->toque('Toque de segundo', 2);
        $deTercero = $this->toque('Toque de tercero', 3);
        $this->actingAs($user);

        $this->get(route('programa.toque.show', $deSegundo))->assertOk();
        $this->get(route('programa.toque.show', $deTercero))->assertForbidden()
            ->assertSee('Este toque se trabaja en')->assertSee('3° año')
            ->assertDontSee('bloqueado');
        $this->get(route('programa.toque.editor', $deTercero))->assertForbidden();
        $this->get(route('programa.partituras.index'))->assertOk()
            ->assertSee('Toque de segundo')->assertDontSee('Toque de tercero')
            ->assertSee('1° a 2° año');
        $this->get(route('programa.index'))->assertOk()->assertSee('Se abre en 3° año');

        Sanctum::actingAs($user);
        $api = $this->getJson('/api/v1/partituras')->assertOk();
        $this->assertSame(2, $api->json('meta.anio_maximo'));
        $this->assertNotContains($deTercero->slug, collect($api->json('data'))->pluck('slug'));
        $this->getJson('/api/v1/partituras/'.$deTercero->slug)->assertForbidden();
    }

    public function test_el_profesor_ve_todo_el_material(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'), 'Bloque lunes', 1);
        $persona = $this->persona('Profe Juan');
        $profe = $this->usuario('Profe Juan', $persona);
        $this->asignarDocente($persona, $bloque);
        $deSexto = $this->toque('Toque de sexto', 6);

        $this->actingAs($profe)->get(route('programa.toque.show', $deSexto))->assertOk();
        $this->get(route('programa.partituras.index'))->assertOk()->assertSee('Toque de sexto')->assertDontSee('1° a ');
    }

    // ---- Guía para dirección ----

    public function test_direccion_tiene_su_guia_paso_a_paso(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('ayuda.direccion'))->assertOk()
            ->assertSee('Guía para dirección')
            ->assertSee('Quiero aprobar los comprobantes que mandan los alumnos')
            ->assertSee('Aprobar y registrar pago')
            ->assertSee(route('comprobantes-cuota-alumnos.index'), false)
            ->assertSee(route('programa.gestion'), false);
        $this->get(route('ayuda'))->assertOk()->assertSee('Abrir la guía para dirección');
        $this->get(route('dashboard'))->assertOk()->assertSee(route('ayuda.direccion'), false);
    }

    public function test_la_guia_de_direccion_no_es_para_profes(): void
    {
        $bloque = $this->bloque($this->sede('Banfield'), 'Bloque jueves', 1);
        $persona = $this->persona('Profe Sol');
        $profe = $this->usuario('Profe Sol', $persona);
        $this->asignarDocente($persona, $bloque);
        $this->actingAs($profe);

        $this->get(route('ayuda.direccion'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('ayuda.direccion'), false);
    }

    // ---- Mi cuota ----

    public function test_el_alumno_sube_su_comprobante_sin_buscarse_en_una_lista(): void
    {
        Storage::fake('comprobantes');
        $alumno = $this->alumnoDeSegundo();
        $user = $this->usuario('Ana Gómez', $alumno->persona);
        Cuota::query()->create([
            'nombre' => 'Cuota octubre', 'año' => (int) now()->year, 'mes' => (int) now()->month,
            'monto' => 15000, 'alcance' => 'general', 'activo' => true,
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee(route('alumno.cuota'), false);
        $this->get(route('alumno.cuota'))->assertOk()
            ->assertSee('Bloque sábado')->assertSee('15.000')->assertSee('Enviar comprobante');

        $this->post(route('alumno.cuota.store'), [
            'alumno_id' => $alumno->id, 'anio' => now()->year, 'mes' => now()->month,
            'fecha_pago' => now()->toDateString(), 'bloque_ids' => [$alumno->bloques->first()->id],
            'comprobante' => UploadedFile::fake()->image('pago.jpg'),
        ])->assertRedirect()->assertSessionHas('success');

        $c = ComprobanteCuotaAlumno::query()->sole();
        $this->assertSame($alumno->id, (int) $c->alumno_id);
        $this->assertSame('pendiente', $c->estado);
        $this->assertSame($user->id, (int) $c->cargado_por_user_id);
        $this->get(route('alumno.cuota'))->assertSee('Lo que ya mandaste')->assertSee('Pendiente de revisión');
    }

    public function test_un_alumno_no_puede_mandar_el_comprobante_de_otro(): void
    {
        Storage::fake('comprobantes');
        $mio = $this->alumnoDeSegundo('30111222');
        $otro = $this->inscribirAlumno($this->persona('Otro Alumno', '30999888'), $mio->bloques->first());
        $this->actingAs($this->usuario('Ana', $mio->persona));

        $this->post(route('alumno.cuota.store'), [
            'alumno_id' => $otro->id, 'anio' => now()->year, 'mes' => now()->month,
            'fecha_pago' => now()->toDateString(), 'bloque_ids' => [$mio->bloques->first()->id],
            'comprobante' => UploadedFile::fake()->image('pago.jpg'),
        ])->assertForbidden();
        $this->assertSame(0, ComprobanteCuotaAlumno::query()->count());
    }
}
