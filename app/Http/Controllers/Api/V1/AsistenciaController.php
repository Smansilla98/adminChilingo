<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Asistencias\AsistenciaService;
use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\SyncOperacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    /**
     * Planilla de una clase: alumnos del bloque con su estado en la fecha.
     */
    public function planilla(Request $request, Bloque $bloque, AsistenciaService $asistencias): JsonResponse
    {
        $this->authorize('verAsistencias', $bloque);
        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();

        $registradas = Asistencia::query()->where('bloque_id', $bloque->id)->whereDate('fecha', $fecha)
            ->get(['alumno_id', 'tipo_asistencia', 'registrado_por', 'updated_at'])->keyBy('alumno_id');

        $alumnos = $asistencias->alumnosDelBloque($bloque)->map(fn ($a) => [
            'alumno_id' => $a->id,
            'nombre' => $a->nombre_apellido,
            'tipo' => $registradas->get($a->id)?->tipo_asistencia,
        ])->values();

        return response()->json([
            'bloque' => ['id' => $bloque->id, 'nombre' => $bloque->nombre],
            'fecha' => $fecha,
            'tomada' => $registradas->isNotEmpty(),
            'puede_editar' => $request->user()->can('tomarAsistencia', $bloque),
            'tipos' => Asistencia::tiposEditables(),
            'alumnos' => $alumnos,
        ]);
    }

    /**
     * Guarda la planilla (total o parcial). Idempotente por `client_uuid`: la app puede
     * reintentar un envío hecho sin conexión sin duplicar ni pisar datos más nuevos.
     */
    public function guardar(Request $request, Bloque $bloque, AsistenciaService $asistencias): JsonResponse
    {
        $this->authorize('tomarAsistencia', $bloque);
        $data = $request->validate([
            'fecha' => 'required|date|before_or_equal:today',
            'client_uuid' => 'nullable|uuid',
            'capturado_en' => 'nullable|date',
            'registros' => 'required|array|min:1|max:200',
            'registros.*.alumno_id' => 'required|integer',
            'registros.*.tipo' => 'required|string|in:'.implode(',', array_keys(Asistencia::tiposEditables())),
        ]);

        if (! empty($data['client_uuid'])) {
            $previa = SyncOperacion::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($previa) {
                return response()->json(($previa->respuesta ?? []) + ['duplicado' => true]);
            }
        }

        $registros = [];
        foreach ($data['registros'] as $r) {
            $registros[(int) $r['alumno_id']] = $r['tipo'];
        }

        $respuesta = DB::transaction(function () use ($bloque, $data, $registros, $asistencias, $request) {
            $capturado = ! empty($data['capturado_en']) ? \Illuminate\Support\Carbon::parse($data['capturado_en']) : null;
            $resultado = $asistencias->registrar($bloque, $data['fecha'], $registros, $request->user(), $capturado);
            if (! empty($data['client_uuid'])) {
                SyncOperacion::query()->create([
                    'client_uuid' => $data['client_uuid'],
                    'user_id' => $request->user()->id,
                    'tipo' => 'asistencia',
                    'respuesta' => $resultado,
                ]);
            }

            return $resultado;
        });

        return response()->json($respuesta + ['duplicado' => false]);
    }
}
