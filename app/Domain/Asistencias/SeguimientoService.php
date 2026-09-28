<?php

namespace App\Domain\Asistencias;

use App\Models\Alumno;
use App\Models\ObservacionPedagogica;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Bitácora pedagógica: notas del docente sobre un alumno (web y API). Solo quien
 * gestiona al alumno escribe; cada nota la borra su autor o administración.
 */
class SeguimientoService
{
    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'alumno_id' => 'required|exists:alumnos,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'fecha' => 'required|date',
            'tipo' => ['required', Rule::in(array_keys(ObservacionPedagogica::TIPOS))],
            'eje' => ['nullable', Rule::in(array_keys(ObservacionPedagogica::EJES))],
            'toque' => 'nullable|string|max:160',
            'cuerpo' => 'required|string|max:4000',
            'proximo_paso' => 'nullable|string|max:400',
            'visible_alumno' => 'nullable|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(User $por, array $datos, bool $visibleAlumno): ObservacionPedagogica
    {
        $alumno = Alumno::query()->findOrFail($datos['alumno_id']);
        $this->autorizarAlumno($por, $alumno);
        if (! empty($datos['bloque_id']) && ! $por->puedeAccederBloque((int) $datos['bloque_id'])) {
            abort(403);
        }
        $fila = [
            'alumno_id' => $alumno->id,
            'user_id' => $por->id,
            'bloque_id' => $datos['bloque_id'] ?? null,
            'fecha' => $datos['fecha'],
            'tipo' => $datos['tipo'],
            'toque' => $datos['toque'] ?? null,
            'cuerpo' => trim($datos['cuerpo']),
        ];
        if (Schema::hasColumn('observaciones_pedagogicas', 'eje')) {
            $fila['eje'] = $datos['eje'] ?? null;
            $fila['proximo_paso'] = isset($datos['proximo_paso']) ? trim((string) $datos['proximo_paso']) : null;
            $fila['visible_alumno'] = $visibleAlumno;
        }

        return ObservacionPedagogica::create($fila);
    }

    public function puedeEliminar(User $user, ObservacionPedagogica $o): bool
    {
        return ($user->isAdmin() || (int) $o->user_id === (int) $user->id) && $o->alumno && $user->puedeGestionarAlumno($o->alumno->loadMissing(['bloques', 'bloque']));
    }

    public function eliminar(User $user, ObservacionPedagogica $o): void
    {
        abort_unless($this->puedeEliminar($user, $o), 403);
        $o->delete();
    }

    public function autorizarAlumno(User $user, Alumno $alumno): void
    {
        $alumno->loadMissing(['bloques', 'bloque']);
        abort_unless($user->puedeGestionarAlumno($alumno), 403);
    }
}
