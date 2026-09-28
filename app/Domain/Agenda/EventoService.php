<?php

namespace App\Domain\Agenda;

use App\Models\Evento;
use App\Models\User;

/**
 * Eventos (web y API). Un evento es de un bloque, de una sede o de toda la escuela, y
 * el ámbito resultante tiene que estar dentro del alcance de quien lo crea o mueve.
 */
class EventoService
{
    public const TIPOS = [
        'show' => 'Show',
        'taller' => 'Taller',
        'muestra' => 'Muestra',
        'muestra_alumnos' => 'Muestra de alumnos',
        'caminata_1er' => 'Caminata de 1er año',
        'show_beneficio' => 'Show a beneficio',
        'gira' => 'Gira',
        'villa_gesell' => 'Villa Gesell',
        'aniversario' => 'Aniversario',
        'fiesta' => 'Fiesta',
        'rifa' => 'Rifa',
        'otro' => 'Otro',
    ];

    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'sede_id' => 'nullable|exists:sedes,id',
            'tipo_evento' => 'required|in:'.implode(',', array_keys(self::TIPOS)),
            'profesor_id' => 'nullable|exists:profesores,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'cantidad_personas' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $por): Evento
    {
        $this->asegurarAmbito($por, 'eventos.create', $datos);

        return Evento::create($datos + ['created_by' => $por->id]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Evento $evento, array $datos, User $por): Evento
    {
        $this->asegurarAmbito($por, 'eventos.update', $datos);
        $evento->update($datos);

        return $evento;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function asegurarAmbito(User $user, string $permiso, array $datos): void
    {
        $acceso = $user->acceso();
        $ok = match (true) {
            ! empty($datos['bloque_id']) => $acceso->puedeEnBloque($permiso, (int) $datos['bloque_id']),
            ! empty($datos['sede_id']) => $acceso->puedeEnSede($permiso, (int) $datos['sede_id']),
            default => $acceso->puedeGlobal($permiso),
        };
        if (! $ok) {
            abort(403, 'No podés crear o mover eventos a ese ámbito.');
        }
    }
}
