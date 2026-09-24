<?php

namespace App\Domain\Datos;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Las FKs heredadas borran en cascada (sede → alumnos → detalle de pagos). Antes de
 * eliminar, verificamos que no haya historial; si lo hay, se ofrece desactivar.
 */
class EliminacionSegura
{
    /**
     * @throws ValidationException
     */
    public function verificar(Model $modelo): void
    {
        $motivos = match (true) {
            $modelo instanceof Sede => $this->dependenciasSede($modelo),
            $modelo instanceof Bloque => $this->dependenciasBloque($modelo),
            $modelo instanceof Alumno => $this->dependenciasAlumno($modelo),
            $modelo instanceof Cuota => $this->contar(['pago_detalles' => ['cuota_id', $modelo->id, 'pagos registrados']]),
            $modelo instanceof Profesor => $this->contar(['bloque_profesor' => ['profesor_id', $modelo->id, 'bloques asignados']]),
            default => [],
        };

        if ($motivos !== []) {
            throw ValidationException::withMessages([
                'eliminar' => 'No se puede eliminar porque tiene '.implode(', ', $motivos)
                    .'. Desactivalo para conservar el historial.',
            ]);
        }
    }

    /** @return list<string> */
    private function dependenciasSede(Sede $sede): array
    {
        return $this->contar([
            'bloques' => ['sede_id', $sede->id, 'bloques'],
            'alumnos' => ['sede_id', $sede->id, 'alumnos'],
            'inventario_items' => ['sede_id', $sede->id, 'ítems de inventario'],
            'facturacion_mensual' => ['sede_id', $sede->id, 'facturación registrada'],
            'ordenes_compra' => ['sede_id', $sede->id, 'órdenes de compra'],
        ]);
    }

    /** @return list<string> */
    private function dependenciasBloque(Bloque $bloque): array
    {
        return $this->contar([
            'asistencias' => ['bloque_id', $bloque->id, 'asistencias'],
            'alumno_bloque' => ['bloque_id', $bloque->id, 'alumnos inscriptos'],
            'comprobante_cuota_alumno_items' => ['bloque_id', $bloque->id, 'comprobantes de pago'],
        ]);
    }

    /** @return list<string> */
    private function dependenciasAlumno(Alumno $alumno): array
    {
        return $this->contar([
            'pago_detalles' => ['alumno_id', $alumno->id, 'pagos'],
            'asistencias' => ['alumno_id', $alumno->id, 'asistencias'],
            'comprobantes_cuota_alumnos' => ['alumno_id', $alumno->id, 'comprobantes'],
        ]);
    }

    /**
     * @param  array<string, array{0: string, 1: int, 2: string}>  $tablas
     * @return list<string>
     */
    private function contar(array $tablas): array
    {
        $out = [];
        foreach ($tablas as $tabla => [$columna, $id, $etiqueta]) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $n = DB::table($tabla)->where($columna, $id)->count();
            if ($n > 0) {
                $out[] = $n.' '.$etiqueta;
            }
        }

        return $out;
    }
}
