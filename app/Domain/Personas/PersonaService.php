<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta, vínculo y fusión de personas. Garantiza una sola identidad por persona.
 */
class PersonaService
{
    /** Evita que la propagación persona → perfiles vuelva a disparar la sincronización inversa. */
    private static bool $propagando = false;

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Persona
    {
        $datos['dni'] = Persona::normalizarDni($datos['dni'] ?? null);
        if ($datos['dni'] && $this->buscarPorDni($datos['dni'])) {
            throw ValidationException::withMessages([
                'dni' => 'Ya existe una persona con ese DNI. Buscala y vinculá la ficha en lugar de crear otra.',
            ]);
        }

        return Persona::query()->create($datos + ['estado' => 'activo']);
    }

    public function buscarPorDni(?string $dni): ?Persona
    {
        $dni = Persona::normalizarDni($dni);
        if (! $dni) {
            return null;
        }

        return Persona::query()->whereNull('fusionada_en_id')->where('dni', $dni)->first();
    }

    /**
     * Ficha de alumno existente para un DNI, sin importar el formato con que se cargó
     * (30.123.456 = 30123456). Evita crear una segunda ficha al inscribir en otro bloque.
     */
    public function alumnoPorDni(?string $dni, ?int $exceptoId = null): ?Alumno
    {
        $normalizado = Persona::normalizarDni($dni);
        if (! $normalizado) {
            return null;
        }
        $excluir = fn ($q) => $exceptoId ? $q->where('id', '!=', $exceptoId) : $q;

        // Vía persona (DNI ya normalizado) y, por si hay fichas sin persona, comparando en PHP.
        $porPersona = Alumno::query()->whereHas('persona', fn ($q) => $q->where('dni', $normalizado))->tap($excluir)->orderBy('id')->first();
        if ($porPersona) {
            return $porPersona;
        }

        return Alumno::query()->whereNotNull('dni')->where('dni', 'like', '%'.substr($normalizado, -4).'%')->tap($excluir)->get()
            ->first(fn (Alumno $a) => Persona::normalizarDni($a->dni) === $normalizado);
    }

    /**
     * Persona del alumno: la vinculada, la del usuario, la del mismo DNI o una nueva.
     */
    public function asegurarParaAlumno(Alumno $alumno): Persona
    {
        if ($alumno->persona_id && ($p = Persona::query()->find($alumno->persona_id))) {
            $this->vincularConCuentaExistente($p);

            return $p;
        }

        $persona = $this->personaDeUsuario($alumno->user_id)
            ?? $this->buscarPorDni($alumno->dni)
            ?? Persona::query()->create([
                'nombre' => (string) $alumno->nombre_apellido,
                'dni' => Persona::normalizarDni($alumno->dni),
                'fecha_nacimiento' => $alumno->fecha_nacimiento,
                'telefono' => $alumno->telefono,
                'estado' => 'activo',
            ]);

        $this->completarVacios($persona, [
            'dni' => Persona::normalizarDni($alumno->dni),
            'fecha_nacimiento' => $alumno->fecha_nacimiento,
            'telefono' => $alumno->telefono,
        ]);
        $alumno->forceFill(['persona_id' => $persona->id])->saveQuietly();
        $this->vincularUsuario($persona, $alumno->user_id);

        return $persona;
    }

    public function asegurarParaProfesor(Profesor $profesor): Persona
    {
        if ($profesor->persona_id && ($p = Persona::query()->find($profesor->persona_id))) {
            $this->vincularConCuentaExistente($p);

            return $p;
        }

        $persona = $this->personaDeUsuario($profesor->user_id)
            ?? Persona::query()->create([
                'nombre' => (string) $profesor->nombre,
                'telefono' => $profesor->telefono,
                'email' => $profesor->email,
                'estado' => 'activo',
            ]);

        $this->completarVacios($persona, ['telefono' => $profesor->telefono, 'email' => $profesor->email]);
        $profesor->forceFill(['persona_id' => $persona->id])->saveQuietly();
        $this->vincularUsuario($persona, $profesor->user_id);

        return $persona;
    }

    public function asegurarParaUsuario(User $user): Persona
    {
        if ($user->persona_id && ($p = Persona::query()->find($user->persona_id))) {
            $this->vincularPerfilesConCuenta($p, $user);

            return $p;
        }

        // ¿Ya existe como alumno o profesor vinculado a esta cuenta?
        $persona = null;
        foreach ([Profesor::class, Alumno::class] as $clase) {
            $perfil = $clase::query()->where('user_id', $user->id)->whereNotNull('persona_id')->first();
            if ($perfil) {
                $persona = Persona::query()->find($perfil->persona_id);
                break;
            }
        }

        $persona ??= Persona::query()->create([
            'nombre' => (string) ($user->name ?: $user->username),
            'email' => $user->email,
            'telefono' => $user->telefono ?? null,
            'estado' => 'activo',
        ]);

        $user->forceFill(['persona_id' => $persona->id])->saveQuietly();
        $this->vincularPerfilesConCuenta($persona, $user);

        return $persona;
    }

    /**
     * Perfiles de la persona sin cuenta ↔ esta cuenta (las vistas legacy navegan por user_id).
     */
    public function vincularPerfilesConCuenta(Persona $persona, User $user): void
    {
        foreach ([Profesor::class, Alumno::class] as $clase) {
            $clase::query()->where('user_id', $user->id)->whereNull('persona_id')->update(['persona_id' => $persona->id]);
            $clase::query()->where('persona_id', $persona->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        }
    }

    /**
     * Cambios en la ficha del alumno → persona (datos personales compartidos).
     */
    public function sincronizarDesdeAlumno(Alumno $alumno): void
    {
        if (self::$propagando || ! $alumno->persona_id) {
            return;
        }
        $persona = Persona::query()->find($alumno->persona_id);
        if (! $persona) {
            return;
        }
        $cambios = [];
        if ($alumno->wasChanged('nombre_apellido') && $alumno->nombre_apellido !== $persona->nombre_completo) {
            $cambios['nombre'] = $alumno->nombre_apellido;
            $cambios['apellido'] = null;
        }
        foreach (['dni' => 'dni', 'fecha_nacimiento' => 'fecha_nacimiento', 'telefono' => 'telefono'] as $desde => $hacia) {
            if ($alumno->wasChanged($desde)) {
                $cambios[$hacia] = $desde === 'dni' ? Persona::normalizarDni($alumno->dni) : $alumno->{$desde};
            }
        }
        if ($cambios !== []) {
            $persona->fill($cambios)->save();
        }
    }

    public function sincronizarDesdeProfesor(Profesor $profesor): void
    {
        if (self::$propagando || ! $profesor->persona_id) {
            return;
        }
        $persona = Persona::query()->find($profesor->persona_id);
        if (! $persona) {
            return;
        }
        $cambios = [];
        if ($profesor->wasChanged('nombre') && $profesor->nombre !== $persona->nombre_completo) {
            $cambios['nombre'] = $profesor->nombre;
            $cambios['apellido'] = null;
        }
        foreach (['telefono', 'email'] as $col) {
            if ($profesor->wasChanged($col) && filled($profesor->{$col})) {
                $cambios[$col] = $profesor->{$col};
            }
        }
        if ($cambios !== []) {
            $persona->fill($cambios)->save();
        }
    }

    /**
     * Persona → columnas equivalentes de sus perfiles (compatibilidad con vistas legacy).
     */
    public function propagar(Persona $persona): void
    {
        if (self::$propagando) {
            return;
        }
        self::$propagando = true;
        try {
            foreach ($persona->alumnos()->get() as $alumno) {
                $alumno->forceFill([
                    'nombre_apellido' => $persona->nombre_completo,
                    'dni' => $persona->dni ?? $alumno->dni,
                    'fecha_nacimiento' => $persona->fecha_nacimiento ?? $alumno->fecha_nacimiento,
                    'telefono' => $persona->telefono ?? $alumno->telefono,
                ])->saveQuietly();
            }
            foreach ($persona->profesores()->get() as $profesor) {
                $profesor->forceFill([
                    'nombre' => $persona->nombre_completo,
                    'telefono' => $persona->telefono ?? $profesor->telefono,
                    'email' => $persona->email ?? $profesor->email,
                ])->saveQuietly();
            }
        } finally {
            self::$propagando = false;
        }
    }

    /**
     * Fusiona $duplicada en $conservar: mueve perfiles, cuenta y asignaciones.
     * La duplicada queda marcada (fusionada_en_id) y dada de baja; no se borra.
     */
    public function fusionar(Persona $conservar, Persona $duplicada): Persona
    {
        if ($conservar->is($duplicada)) {
            throw ValidationException::withMessages(['persona' => 'No se puede fusionar una persona consigo misma.']);
        }
        $userConservar = $conservar->user;
        $userDuplicada = $duplicada->user;
        if ($userConservar && $userDuplicada) {
            throw ValidationException::withMessages([
                'persona' => 'Las dos personas tienen cuenta de usuario. Desactivá una de las cuentas y desvinculala antes de fusionar.',
            ]);
        }

        return DB::transaction(function () use ($conservar, $duplicada, $userDuplicada) {
            $antes = ['conservar' => $conservar->id, 'duplicada' => $duplicada->id];

            Alumno::query()->where('persona_id', $duplicada->id)->update(['persona_id' => $conservar->id]);
            Profesor::query()->where('persona_id', $duplicada->id)->update(['persona_id' => $conservar->id]);
            Asignacion::query()->where('persona_id', $duplicada->id)->update(['persona_id' => $conservar->id]);
            if ($userDuplicada) {
                $userDuplicada->forceFill(['persona_id' => $conservar->id])->saveQuietly();
            }

            $this->completarVacios($conservar, $duplicada->only([
                'apellido', 'dni', 'fecha_nacimiento', 'telefono', 'email', 'direccion',
                'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'foto_path',
            ]));

            $duplicada->forceFill(['fusionada_en_id' => $conservar->id, 'estado' => 'baja'])->save();
            $duplicada->delete();

            Auditoria::registrar('fusionada', $conservar, $antes, ['persona_final' => $conservar->id]);

            return $conservar->fresh();
        });
    }

    /** Si la persona ya tiene cuenta, sus perfiles nuevos quedan vinculados a esa cuenta. */
    private function vincularConCuentaExistente(Persona $persona): void
    {
        $user = User::query()->where('persona_id', $persona->id)->first();
        if ($user) {
            $this->vincularPerfilesConCuenta($persona, $user);
        }
    }

    private function personaDeUsuario(?int $userId): ?Persona
    {
        if (! $userId) {
            return null;
        }
        $personaId = User::query()->whereKey($userId)->value('persona_id');

        return $personaId ? Persona::query()->find($personaId) : null;
    }

    private function vincularUsuario(Persona $persona, ?int $userId): void
    {
        if ($userId) {
            User::query()->whereKey($userId)->whereNull('persona_id')->update(['persona_id' => $persona->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function completarVacios(Persona $persona, array $datos): void
    {
        $cambios = [];
        foreach ($datos as $campo => $valor) {
            if (blank($persona->{$campo}) && filled($valor)) {
                $cambios[$campo] = $valor;
            }
        }
        if ($cambios !== []) {
            $persona->forceFill($cambios)->saveQuietly();
        }
    }
}
