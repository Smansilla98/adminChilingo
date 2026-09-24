<?php

namespace App\Domain\Asistencias;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toma de asistencia de una clase (bloque + fecha). Idempotente: una fila por
 * alumno/bloque/fecha, así que reenviar la misma planilla (p. ej. desde la app sin
 * conexión) no duplica.
 */
class AsistenciaService
{
    /**
     * Alumnos activos del bloque (pivot + columna legacy), ordenados.
     *
     * @return Collection<int, Alumno>
     */
    public function alumnosDelBloque(Bloque $bloque): Collection
    {
        return Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('bloque_id', $bloque->id)
                ->orWhereHas('bloques', fn ($b) => $b->where('bloques.id', $bloque->id)))
            ->orderBy('nombre_apellido')
            ->get();
    }

    /**
     * @param  array<int|string, string>  $registros  alumno_id => tipo_asistencia
     * @param  \DateTimeInterface|null  $capturadoEn  cuándo se tomó en el dispositivo (sincronización offline):
     *                                                si otra persona la modificó después, se conserva el dato del servidor.
     * @return array{guardadas: int, fecha: string, bloque_id: int, conflictos: list<int>}
     */
    public function registrar(Bloque $bloque, string $fecha, array $registros, ?User $por, ?\DateTimeInterface $capturadoEn = null): array
    {
        $validos = array_keys(Asistencia::tiposEditables());
        $idsDelBloque = $this->alumnosDelBloque($bloque)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ajenos = array_diff(array_map('intval', array_keys($registros)), $idsDelBloque);
        if ($ajenos !== []) {
            throw ValidationException::withMessages([
                'asistencias' => 'Hay alumnos que no pertenecen a este bloque (IDs: '.implode(', ', $ajenos).').',
            ]);
        }

        $guardadas = 0;
        $conflictos = [];
        DB::transaction(function () use ($bloque, $fecha, $registros, $validos, $por, $capturadoEn, &$guardadas, &$conflictos) {
            foreach ($registros as $alumnoId => $tipo) {
                if (! in_array($tipo, $validos, true)) {
                    throw ValidationException::withMessages(['asistencias' => 'Tipo de asistencia inválido: '.$tipo]);
                }
                // whereDate: la columna es DATE en MySQL pero puede guardarse con hora en SQLite.
                $asistencia = Asistencia::query()
                    ->where('alumno_id', (int) $alumnoId)
                    ->where('bloque_id', $bloque->id)
                    ->whereDate('fecha', $fecha)
                    ->first()
                    ?? new Asistencia(['alumno_id' => (int) $alumnoId, 'bloque_id' => $bloque->id, 'fecha' => $fecha]);
                if ($capturadoEn && $asistencia->exists && $asistencia->updated_at?->gt($capturadoEn)
                    && (int) $asistencia->registrado_por !== (int) $por?->id) {
                    $conflictos[] = (int) $alumnoId;

                    continue;
                }
                $asistencia->tipo_asistencia = $tipo;
                $asistencia->presente = Asistencia::esPresente($tipo);
                if ($por && ($asistencia->isDirty() || ! $asistencia->exists)) {
                    $asistencia->registrado_por = $por->id;
                }
                $asistencia->save();
                $guardadas++;
            }
        });

        return ['guardadas' => $guardadas, 'fecha' => $fecha, 'bloque_id' => (int) $bloque->id, 'conflictos' => $conflictos];
    }
}
