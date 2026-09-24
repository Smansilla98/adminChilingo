<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Una persona es una sola identidad, aunque cumpla muchas funciones.
 */
class IdentidadMultirrolTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_persona_puede_ser_alumno_y_profesor_con_una_sola_cuenta(): void
    {
        $sede = $this->sede('Banfield');
        $b1 = $this->bloque($sede, 'B1');
        $b2 = $this->bloque($sede, 'B2');
        $user = $this->usuario('María Gómez');

        $this->inscribirAlumno($user->persona, $b1);
        $this->asignarDocente($user->persona, $b2);

        $user = $user->fresh();
        $this->assertTrue($user->isAlumno());
        $this->assertTrue($user->isProfesor());
        $this->assertSame(1, Persona::query()->where('nombre', 'María Gómez')->count());
        $this->assertSame(1, User::query()->where('persona_id', $user->persona_id)->count());
        $this->assertSame($user->persona_id, Alumno::query()->first()->persona_id);
        $this->assertSame($user->persona_id, Profesor::query()->first()->persona_id);
        // Los perfiles quedan vinculados a la cuenta (las vistas heredadas navegan por user_id).
        $this->assertSame($user->id, Profesor::query()->first()->user_id);
        $this->assertSame($user->id, Alumno::query()->first()->user_id);
    }

    public function test_persona_puede_estar_en_varias_sedes_y_bloques(): void
    {
        $banfield = $this->sede('Banfield');
        $palomar = $this->sede('Palomar');
        $persona = $this->persona('Juan');
        $alumno = $this->inscribirAlumno($persona, $this->bloque($banfield, 'B3'));
        $this->inscribirAlumno($persona, $this->bloque($palomar, 'P1'));

        $this->assertSame(2, $alumno->fresh()->bloques()->count());
        $this->assertSame(1, $persona->alumnos()->count());
    }

    public function test_alumno_por_dni_reutiliza_la_persona_existente(): void
    {
        $sede = $this->sede('Quilmes');
        $persona = $this->persona('Ana', '30.123.456');

        $alumno = Alumno::query()->create([
            'nombre_apellido' => 'Ana Pérez',
            'dni' => '30123456',
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $this->assertSame($persona->id, $alumno->fresh()->persona_id);
        $this->assertSame(1, Persona::query()->count());
    }

    public function test_crear_profesor_desde_persona_alumna_no_duplica(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Varela');
        $persona = $this->persona('Lucía');
        $alumno = $this->inscribirAlumno($persona, $this->bloque($sede));

        $this->actingAs($admin)->post(route('profesores.store'), [
            'persona_id' => $persona->id,
            'nombre' => 'Lucía',
            'activo' => '1',
            'cuenta_modo' => 'ninguna',
        ])->assertRedirect();

        $this->assertSame(1, Persona::query()->where('nombre', 'Lucía')->count());
        $this->assertSame($persona->id, Profesor::query()->firstOrFail()->persona_id);
        $this->assertSame($persona->id, $alumno->fresh()->persona_id);
    }

    public function test_editar_persona_propaga_a_sus_fichas(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Saavedra');
        $persona = $this->persona('Pedro');
        $this->inscribirAlumno($persona, $this->bloque($sede));
        $this->asignarDocente($persona, $this->bloque($sede, 'Otro'));

        $this->actingAs($admin)->put(route('personas.update', $persona), [
            'nombre' => 'Pedro', 'apellido' => 'Suárez', 'telefono' => '1122334455', 'estado' => 'activo',
        ])->assertRedirect();

        $this->assertSame('Pedro Suárez', Alumno::query()->first()->nombre_apellido);
        $this->assertSame('Pedro Suárez', Profesor::query()->first()->nombre);
        $this->assertSame('1122334455', Profesor::query()->first()->telefono);
    }

    public function test_fusionar_personas_une_fichas_y_cuenta(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Tacheles');
        $conCuenta = $this->usuario('Caro')->persona;
        $this->asignarDocente($conCuenta, $this->bloque($sede));
        $duplicada = $this->persona('Carolina', '28999111');
        $alumno = $this->inscribirAlumno($duplicada, $this->bloque($sede, 'B2'));

        $this->actingAs($admin)->post(route('personas.fusionar', $conCuenta), [
            'persona_id' => $conCuenta->id,
            'duplicada_id' => $duplicada->id,
        ])->assertRedirect(route('personas.show', $conCuenta));

        $this->assertSame($conCuenta->id, $alumno->fresh()->persona_id);
        $this->assertSame('28999111', $conCuenta->fresh()->dni);
        $this->assertSoftDeleted('personas', ['id' => $duplicada->id]);
        $this->assertTrue($conCuenta->user->fresh()->isAlumno());
    }

    public function test_no_se_puede_crear_segunda_cuenta_para_la_misma_persona(): void
    {
        $admin = $this->admin();
        $persona = $this->usuario('Única')->persona;

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'persona_id' => $persona->id,
            'username' => 'otra-cuenta',
            'email' => 'otra@test.local',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertSessionHasErrors('persona_id');

        $this->assertSame(1, User::query()->where('persona_id', $persona->id)->count());
    }
}
