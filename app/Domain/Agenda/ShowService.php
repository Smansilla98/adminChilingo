<?php

namespace App\Domain\Agenda;

use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shows y convocatorias de bloques (web y API). Sin alcance global solo se
 * convoca a bloques propios.
 */
class ShowService
{
    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after_or_equal:hora_inicio',
            'lugar' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'convocatoria_abierta' => 'boolean',
            'bloque_ids' => 'nullable|array',
            'bloque_ids.*' => 'exists:bloques,id',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados, con convocatoria_abierta resuelto
     */
    public function guardar(?Show $show, array $datos, User $por): Show
    {
        $bloques = array_map('intval', $datos['bloque_ids'] ?? []);
        $this->asegurarBloquesGestionables($por, $bloques);

        return DB::transaction(function () use ($show, $datos, $bloques) {
            $campos = [
                'titulo' => $datos['titulo'],
                'fecha' => $datos['fecha'],
                'hora_inicio' => ! empty($datos['hora_inicio']) ? $datos['hora_inicio'].':00' : null,
                'hora_fin' => ! empty($datos['hora_fin']) ? $datos['hora_fin'].':00' : null,
                'lugar' => $datos['lugar'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'convocatoria_abierta' => (bool) ($datos['convocatoria_abierta'] ?? false),
            ];
            if ($show) {
                $show->update($campos);
                $show->bloques()->sync($bloques);
            } else {
                $show = Show::create($campos);
                if ($bloques !== []) {
                    $show->bloques()->sync($bloques);
                }
            }

            return $show;
        });
    }

    public function eliminar(Show $show): void
    {
        DB::transaction(function () use ($show) {
            $show->bloques()->detach();
            $show->delete();
        });
    }

    /**
     * @param  list<int>  $bloqueIds
     */
    private function asegurarBloquesGestionables(User $user, array $bloqueIds): void
    {
        $acceso = $user->acceso();
        if ($acceso->puedeGlobal('shows.manage')) {
            return;
        }
        if ($bloqueIds === []) {
            abort(403, 'Elegí al menos un bloque de tu alcance.');
        }
        foreach ($bloqueIds as $id) {
            if (! $acceso->puedeEnBloque('shows.manage', $id)) {
                abort(403, 'No podés convocar bloques fuera de tu alcance.');
            }
        }
    }
}
