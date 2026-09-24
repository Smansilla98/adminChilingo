<?php

namespace Tests\Support;

use App\Models\Alumno;
use App\Models\Asignacion;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Helpers para armar escenarios multi-sede / multi-rol con datos reales.
 */
trait Escenarios
{
    protected function sede(string $nombre): Sede
    {
        return Sede::query()->create(['nombre' => $nombre, 'activo' => true]);
    }

    protected function bloque(Sede $sede, string $nombre = 'Bloque', int $anio = 1): Bloque
    {
        return Bloque::query()->create([
            'nombre' => $nombre,
            'año' => $anio,
            'sede_id' => $sede->id,
            'cantidad_max_alumnos' => 30,
            'activo' => true,
        ]);
    }

    protected function persona(string $nombre = 'Persona', ?string $dni = null): Persona
    {
        return Persona::query()->create(['nombre' => $nombre, 'dni' => $dni, 'estado' => 'activo']);
    }

    /** Persona con cuenta de acceso. */
    protected function usuario(string $nombre = 'Usuario', ?Persona $persona = null): User
    {
        $slug = Str::slug($nombre).'-'.Str::lower(Str::random(5));
        $user = User::query()->create([
            'name' => $nombre,
            'username' => $slug,
            'email' => $slug.'@test.local',
            'password' => Hash::make('password'),
            'role' => 'usuario',
            'persona_id' => $persona?->id,
        ]);

        return $user->fresh();
    }

    protected function inscribirAlumno(Persona $persona, Bloque $bloque): Alumno
    {
        $alumno = Alumno::query()->where('persona_id', $persona->id)->first()
            ?? Alumno::query()->create([
                'persona_id' => $persona->id,
                'nombre_apellido' => $persona->nombre_completo,
                'sede_id' => $bloque->sede_id,
                'bloque_id' => $bloque->id,
                'activo' => true,
            ]);
        $alumno->bloques()->syncWithoutDetaching([$bloque->id => ['es_principal' => $alumno->bloques()->count() === 0]]);

        return $alumno->fresh();
    }

    protected function asignarDocente(Persona $persona, Bloque $bloque, string $rol = 'titular'): Profesor
    {
        $profesor = Profesor::query()->where('persona_id', $persona->id)->first()
            ?? Profesor::query()->create([
                'persona_id' => $persona->id,
                'user_id' => $persona->user?->id,
                'nombre' => $persona->nombre_completo,
                'activo' => true,
            ]);
        $profesor->bloques()->syncWithoutDetaching([$bloque->id => ['rol' => $rol]]);

        return $profesor->fresh();
    }

    /** Rol de sede derivado de la ficha docente (profesor_sede). */
    protected function rolDocenteEnSede(Persona $persona, Sede $sede, string $rol): Profesor
    {
        $profesor = Profesor::query()->where('persona_id', $persona->id)->first()
            ?? Profesor::query()->create([
                'persona_id' => $persona->id,
                'user_id' => $persona->user?->id,
                'nombre' => $persona->nombre_completo,
                'activo' => true,
            ]);
        DB::table('profesor_sede')->insert([
            'profesor_id' => $profesor->id, 'sede_id' => $sede->id, 'rol' => $rol,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $profesor;
    }

    protected function asignarRol(Persona $persona, string $rol, string $ambito = 'global', ?Sede $sede = null, ?Bloque $bloque = null): Asignacion
    {
        return Asignacion::query()->create([
            'persona_id' => $persona->id,
            'role_id' => Role::findByName($rol, 'web')->id,
            'ambito_tipo' => $ambito,
            'sede_id' => $sede?->id,
            'bloque_id' => $bloque?->id,
            'activo' => true,
        ]);
    }

    protected function asignarPermiso(Persona $persona, string $permiso, string $ambito = 'global', ?Sede $sede = null): Asignacion
    {
        return Asignacion::query()->create([
            'persona_id' => $persona->id,
            'permission_id' => Permission::findByName($permiso, 'web')->id,
            'ambito_tipo' => $ambito,
            'sede_id' => $sede?->id,
            'activo' => true,
        ]);
    }

    protected function admin(string $nombre = 'Admin'): User
    {
        $user = $this->usuario($nombre);
        $this->asignarRol($user->persona, 'administrador');

        return $user->fresh();
    }
}
