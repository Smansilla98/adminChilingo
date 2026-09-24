<?php

namespace Tests\Feature;

use App\Domain\Datos\Diagnostico;
use App\Domain\Personas\BackfillPersonas;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La migración a Personas conserva los datos existentes y no adivina identidades.
 */
class MigracionDatosTest extends TestCase
{
    use RefreshDatabase;

    /** Inserta filas "como estaban antes": sin persona y sin disparar observers. */
    private function legacy(): array
    {
        $now = now();
        $sede = DB::table('sedes')->insertGetId(['nombre' => 'Banfield', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $bloque = DB::table('bloques')->insertGetId(['nombre' => 'B1', 'año' => 1, 'sede_id' => $sede, 'cantidad_max_alumnos' => 20, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $user = fn (string $u, string $role) => DB::table('users')->insertGetId([
            'name' => ucfirst($u), 'username' => $u, 'email' => $u.'@x.test', 'password' => Hash::make('x'), 'role' => $role, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $uMaria = $user('maria', 'profesor');
        $uDire = $user('direccion', 'admin');
        $profMaria = DB::table('profesores')->insertGetId(['nombre' => 'María Gómez', 'user_id' => $uMaria, 'email' => 'maria@x.test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $alumMaria = DB::table('alumnos')->insertGetId(['nombre_apellido' => 'María Gómez', 'dni' => '30.111.222', 'user_id' => $uMaria, 'sede_id' => $sede, 'bloque_id' => $bloque, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        // Mismo DNI cargado dos veces como alumno (sin cuenta) → misma persona.
        $alumA = DB::table('alumnos')->insertGetId(['nombre_apellido' => 'Pedro Ruiz', 'dni' => '40111222', 'sede_id' => $sede, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $alumB = DB::table('alumnos')->insertGetId(['nombre_apellido' => 'Pedro Ruiz (2)', 'dni' => '40.111.222', 'sede_id' => $sede, 'activo' => false, 'created_at' => $now, 'updated_at' => $now]);
        // Homónimos sin DNI: NO se fusionan solos.
        $homA = DB::table('alumnos')->insertGetId(['nombre_apellido' => 'Juan Pérez', 'sede_id' => $sede, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $homB = DB::table('alumnos')->insertGetId(['nombre_apellido' => 'Juan Pérez', 'sede_id' => $sede, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $profSolo = DB::table('profesores')->insertGetId(['nombre' => 'Sin Cuenta', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);

        return compact('uMaria', 'uDire', 'profMaria', 'alumMaria', 'alumA', 'alumB', 'homA', 'homB', 'profSolo');
    }

    public function test_backfill_une_por_cuenta_y_dni_sin_adivinar_homonimos(): void
    {
        $ids = $this->legacy();
        $stats = app(BackfillPersonas::class)->ejecutar();

        $persona = fn (string $tabla, int $id) => (int) DB::table($tabla)->where('id', $id)->value('persona_id');

        // María: cuenta + ficha docente + ficha alumna = una persona.
        $this->assertSame($persona('users', $ids['uMaria']), $persona('profesores', $ids['profMaria']));
        $this->assertSame($persona('users', $ids['uMaria']), $persona('alumnos', $ids['alumMaria']));
        $this->assertSame('30111222', Persona::query()->find($persona('users', $ids['uMaria']))->dni);
        // Mismo DNI con distinto formato → misma persona.
        $this->assertSame($persona('alumnos', $ids['alumA']), $persona('alumnos', $ids['alumB']));
        // Homónimos sin DNI → personas distintas (las reporta el diagnóstico).
        $this->assertNotSame($persona('alumnos', $ids['homA']), $persona('alumnos', $ids['homB']));
        // Nada queda sin persona.
        foreach (['users', 'profesores', 'alumnos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->whereNull('persona_id')->count(), $tabla);
        }
        // Dirección heredada conserva todos sus privilegios.
        $this->assertTrue(User::query()->find($ids['uDire'])->acceso()->esSuperadmin());
        $this->assertSame(1, $stats['admins']);

        // Idempotente.
        $again = app(BackfillPersonas::class)->ejecutar();
        $this->assertSame(0, $again['personas_creadas']);

        $diag = app(Diagnostico::class)->ejecutar();
        $this->assertCount(1, $diag['personas_duplicadas_nombre']['items']);
        $this->assertSame([], $diag['perfiles_sin_persona']['items']);
    }

    public function test_simulacion_no_escribe(): void
    {
        $this->legacy();
        $stats = app(BackfillPersonas::class)->ejecutar(simular: true);

        $this->assertGreaterThan(0, $stats['personas_creadas']);
        $this->assertSame(0, Persona::query()->count());
    }

    public function test_diagnostico_detecta_pagos_inconsistentes_y_referencias_rotas(): void
    {
        $now = now();
        $cuota = DB::table('cuotas')->insertGetId(['nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 100, 'alcance' => 'bloque', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('pagos')->insert(['fecha_pago' => $now, 'monto_total' => 500, 'created_at' => $now, 'updated_at' => $now]);

        $diag = app(Diagnostico::class)->ejecutar();

        $this->assertNotEmpty($diag['pagos_inconsistentes']['items']);   // pago sin detalle
        $this->assertNotEmpty($diag['cuotas_inconsistentes']['items']);  // alcance bloque sin bloque
        $this->artisan('chilinga:diagnose')->assertSuccessful();
        $this->artisan('chilinga:diagnose', ['--strict' => true])->assertFailed();
        $this->assertSame($cuota, (int) DB::table('cuotas')->value('id'));
    }
}
