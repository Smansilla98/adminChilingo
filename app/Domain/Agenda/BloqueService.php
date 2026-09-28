<?php

namespace App\Domain\Agenda;

use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\User;

/**
 * Bloques (grupos de clase) y sus horarios semanales. Web y API. Crear o mover un
 * bloque a una sede requiere `bloques.manage` en esa sede.
 */
class BloqueService
{
    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:1|max:6',
            'profesor_id' => 'nullable|exists:profesores,id',
            'corresponde_a' => 'nullable|string|max:255',
            'sede_id' => 'required|exists:sedes,id',
            'cantidad_max_alumnos' => 'required|integer|min:1',
            'tambores' => 'nullable|array',
            'tambores.*' => 'string|max:100',
            'activo' => 'boolean',
        ];
    }

    /**
     * Misma validación con `anio` en lugar de `año` (la API evita la ñ en los nombres de campo).
     *
     * @return array<string, mixed>
     */
    public function reglasApi(): array
    {
        $reglas = $this->reglas();
        $reglas['anio'] = $reglas['año'];
        unset($reglas['año']);

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function desdeApi(array $datos): array
    {
        $datos['año'] = $datos['anio'];
        unset($datos['anio']);

        return $datos;
    }

    /** @return array<string, mixed> */
    public function reglasHorario(): array
    {
        return [
            'dia_semana' => 'required|integer|min:1|max:7',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados, con activo resuelto
     */
    public function crear(array $datos, User $por): Bloque
    {
        $this->asegurarSedeGestionable($por, (int) $datos['sede_id']);
        $bloque = Bloque::create($this->normalizar($datos));
        $bloque->syncProfesorTitularEnPivot();

        return $bloque;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Bloque $bloque, array $datos, User $por): Bloque
    {
        if ((int) $datos['sede_id'] !== (int) $bloque->sede_id) {
            $this->asegurarSedeGestionable($por, (int) $datos['sede_id']);
        }
        $bloque->update($this->normalizar($datos));
        $bloque->syncProfesorTitularEnPivot();

        return $bloque;
    }

    /**
     * @param  array{dia_semana: int, hora_inicio: string, hora_fin: string}  $datos
     */
    public function agregarHorario(Bloque $bloque, array $datos): BloqueHorario
    {
        return $bloque->horarios()->create([
            'dia_semana' => $datos['dia_semana'],
            'hora_inicio' => $datos['hora_inicio'].':00',
            'hora_fin' => $datos['hora_fin'].':00',
        ]);
    }

    public function asegurarSedeGestionable(User $user, int $sedeId): void
    {
        if (! $user->acceso()->puedeEnSede('bloques.manage', $sedeId)) {
            abort(403, 'No podés gestionar bloques en esa sede.');
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $datos['tambores'] = ! empty($datos['tambores']) ? array_values($datos['tambores']) : null;

        return $datos;
    }
}
