<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Inscripción y edición de fichas de alumno (web y API): datos, sede, bloques (uno
 * principal), vínculo con la ficha docente de la misma persona. Un DNI = una persona.
 */
class AlumnoService
{
    public const TIPOS_TAMBOR = ['Redoblante', 'Repique', 'Medio', 'Fondo Agudo', 'Fondo Grave', 'Timbal', 'Platillo', 'Otro'];

    public const TAMBOR_PROCEDENCIAS = ['Propio', 'Sede'];

    public function __construct(private PersonaService $personas) {}

    /** @return array<string, mixed> */
    public function reglas(?Alumno $alumno = null): array
    {
        $reglas = [
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:alumnos,dni'.($alumno ? ','.$alumno->id : '').'|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'nullable|string|in:'.implode(',', self::TIPOS_TAMBOR),
            'tambor_procedencia' => 'nullable|string|in:'.implode(',', self::TAMBOR_PROCEDENCIAS),
            'bloque_ids' => 'nullable|array',
            'bloque_ids.*' => 'exists:bloques,id',
            'bloque_principal_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
            'crear_perfil_profesor' => 'nullable|boolean',
            'vincular_profesor_id' => 'nullable|exists:profesores,id',
        ];
        if (! $alumno) {
            $reglas['persona_id'] = 'nullable|exists:personas,id';
        }

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglas(), con `activo` resuelto
     */
    public function guardar(?Alumno $alumno, array $datos, User $por): Alumno
    {
        // Mismo DNI en otro formato = misma persona: se agrega el bloque a su ficha, no se crea otra.
        if ($existente = $this->personas->alumnoPorDni($datos['dni'] ?? null, $alumno?->id)) {
            throw ValidationException::withMessages([
                'dni' => "Ese DNI ya es de {$existente->nombre_apellido}. Para inscribirlo en otro bloque, editá su ficha y agregá el bloque.",
            ]);
        }

        $bloqueIds = array_values(array_map('intval', $datos['bloque_ids'] ?? []));
        $this->asegurarSedeYBloquesPermitidos($por, $alumno ? 'alumnos.update' : 'alumnos.create', (int) $datos['sede_id'], $bloqueIds);
        $principalId = ! empty($datos['bloque_principal_id']) ? (int) $datos['bloque_principal_id'] : ($bloqueIds[0] ?? null);
        $crearProfesor = (bool) ($datos['crear_perfil_profesor'] ?? false);
        $vincularProfesorId = (int) ($datos['vincular_profesor_id'] ?? 0);

        if (! $alumno && ! empty($datos['persona_id'])) {
            // Inscribir a una persona existente (ej. un profesor que empieza a cursar).
            $persona = Persona::query()->findOrFail($datos['persona_id']);
            Gate::forUser($por)->authorize('view', $persona);
            if ($persona->alumnos()->exists()) {
                throw ValidationException::withMessages(['persona_id' => 'Esta persona ya tiene ficha de alumno. Agregale el bloque desde su ficha.']);
            }
        }

        $campos = array_intersect_key($datos, array_flip([
            'nombre_apellido', 'dni', 'fecha_nacimiento', 'telefono', 'instrumento_principal', 'instrumento_secundario',
            'tipo_tambor', 'tambor_procedencia', 'sede_id', 'activo', 'persona_id',
        ]));
        $campos['bloque_id'] = $principalId;

        return DB::transaction(function () use ($alumno, $campos, $bloqueIds, $principalId, $crearProfesor, $vincularProfesorId) {
            if ($alumno) {
                unset($campos['persona_id']);
                $alumno->update($campos);
            } else {
                $alumno = Alumno::create($campos);
            }
            $this->sincronizarBloques($alumno, $bloqueIds, $principalId);
            $this->vincularPerfilProfesor($alumno, $crearProfesor, $vincularProfesorId);

            return $alumno;
        });
    }

    /**
     * @param  list<int>  $bloqueIds
     */
    private function sincronizarBloques(Alumno $alumno, array $bloqueIds, ?int $principalId): void
    {
        if (! Schema::hasTable('alumno_bloque')) {
            return;
        }
        $bloqueIds = array_values(array_unique(array_filter($bloqueIds)));
        if ($principalId && ! in_array($principalId, $bloqueIds, true)) {
            $bloqueIds[] = $principalId;
        }
        if ($principalId === null && $bloqueIds !== []) {
            $principalId = $bloqueIds[0];
        }
        $sync = [];
        foreach ($bloqueIds as $bid) {
            $sync[$bid] = ['es_principal' => $principalId && (int) $bid === (int) $principalId];
        }
        $alumno->bloques()->sync($sync);
    }

    private function vincularPerfilProfesor(Alumno $alumno, bool $crear, int $profesorId): void
    {
        if ($alumno->profesorPerfil()) {
            return;
        }
        if ($profesorId > 0) {
            // "Este alumno es este profesor": misma persona.
            $prof = Profesor::query()->find($profesorId);
            $personaAlumno = $alumno->persona;
            $personaProfe = $prof?->persona;
            if ($prof && $personaAlumno && $personaProfe && ! $personaAlumno->is($personaProfe)) {
                [$conservar, $duplicada] = $personaProfe->user ? [$personaProfe, $personaAlumno] : [$personaAlumno, $personaProfe];
                $this->personas->fusionar($conservar, $duplicada);
            }
            $prof?->sincronizarRolesUsuario();

            return;
        }
        if (! $crear) {
            return;
        }
        $prof = Profesor::create([
            'persona_id' => $alumno->persona_id,
            'nombre' => $alumno->nombre_apellido,
            'telefono' => $alumno->telefono,
            'email' => null,
            'activo' => $alumno->activo,
            'user_id' => $alumno->user_id,
        ]);
        $prof->sincronizarRolesUsuario();
    }

    /**
     * La sede principal y cada bloque tienen que estar dentro del alcance del permiso.
     *
     * @param  list<int>  $bloqueIds
     */
    private function asegurarSedeYBloquesPermitidos(User $user, string $permiso, int $sedeId, array $bloqueIds): void
    {
        $acceso = $user->acceso();
        $alcance = $acceso->alcance($permiso);
        if ($alcance->esGlobal()) {
            return;
        }
        // La sede principal alcanza si la gestiona o si da clase en un bloque de esa sede.
        if (! in_array($sedeId, $alcance->sedesTocadas(), true)) {
            abort(403, 'No podés asignar alumnos a esa sede.');
        }
        foreach ($bloqueIds as $bloqueId) {
            if (! $acceso->puedeEnBloque($permiso, $bloqueId)) {
                abort(403, 'No podés asignar alumnos a ese bloque.');
            }
        }
    }
}
