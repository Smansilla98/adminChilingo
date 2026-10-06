<?php

namespace App\Domain\Programa;

use App\Models\Auditoria;
use App\Models\ProgramaRitmo;
use Illuminate\Support\Facades\DB;

/**
 * Cambios de administración sobre un toque del programa: año, nombre (con historial),
 * si se sigue tocando y si forma parte del programa. El slug no cambia, así los
 * enlaces y las partituras compartidas siguen funcionando después de un renombre.
 */
class GestionProgramaService
{
    /**
     * @param  array{nombre: string, año: int, vigente: bool, en_programa: bool, estado_nota?: string|null}  $datos
     * @return array<string, mixed> campos que cambiaron
     */
    public function actualizar(ProgramaRitmo $toque, array $datos): array
    {
        return DB::transaction(function () use ($toque, $datos) {
            $toque->refresh();
            $antes = $toque->only(['nombre', 'año', 'orden', 'vigente', 'en_programa', 'estado_nota']);

            $nombre = trim(preg_replace('/\s+/u', ' ', $datos['nombre']));
            if ($nombre !== $toque->nombre) {
                // Una corrección de mayúsculas no es un cambio de nombre.
                if (mb_strtolower($nombre) !== mb_strtolower($toque->nombre)) {
                    $toque->nombres_anteriores = $this->historialConRenombre($toque, $nombre);
                }
                $toque->nombre = $nombre;
            }

            $año = (int) $datos['año'];
            if ($año !== (int) $toque->año) {
                // Pasa al final de su nuevo año; el orden del año anterior no se toca.
                $toque->orden = max(1, (int) ProgramaRitmo::query()->where('año', $año)->max('orden') + 1);
                $toque->año = $año;
            }

            $toque->vigente = (bool) $datos['vigente'];
            $toque->en_programa = (bool) $datos['en_programa'];
            $nota = trim((string) ($datos['estado_nota'] ?? ''));
            $toque->estado_nota = $nota !== '' ? $nota : null;

            if (! $toque->isDirty()) {
                return [];
            }
            $toque->save();

            $despues = $toque->only(array_keys($antes));
            $cambios = array_filter($despues, fn ($v, $k) => $antes[$k] !== $v, ARRAY_FILTER_USE_BOTH);
            Auditoria::registrar('programa.toque.gestion', $toque, array_intersect_key($antes, $cambios), $cambios);

            return $cambios;
        });
    }

    /**
     * Guarda el nombre actual en el historial. Si el nombre nuevo ya estaba en el
     * historial (se vuelve a un nombre viejo), sale de ahí para no repetirlo.
     *
     * @return list<array{nombre: string, hasta: string}>|null
     */
    private function historialConRenombre(ProgramaRitmo $toque, string $nuevo): ?array
    {
        $historial = collect($toque->historialNombres())
            ->reject(fn ($n) => mb_strtolower($n['nombre']) === mb_strtolower($nuevo)
                || mb_strtolower($n['nombre']) === mb_strtolower($toque->nombre))
            ->push(['nombre' => $toque->nombre, 'hasta' => now()->toDateString()])
            ->values()
            ->all();

        return $historial !== [] ? $historial : null;
    }
}
