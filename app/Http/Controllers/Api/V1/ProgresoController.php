<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Pedagogia\ProgresoService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\EvaluacionToque;
use App\Models\ProgramaRitmo;
use App\Models\ProgresoToque;
use App\Models\PromocionAnio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Progreso por toque y año para la app (docente, coordinación y alumno). */
class ProgresoController extends Controller
{
    public function __construct(private ProgresoService $progreso) {}

    /** Bloques que la persona puede mirar. */
    public function bloques(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->progreso->bloquesVisibles($user)->get(['id', 'nombre', 'año', 'sede_id'])->map(fn (Bloque $b) => [
            'id' => $b->id,
            'nombre' => $b->nombre,
            'anio' => $b->año ? (int) $b->año : null,
            'sede' => $b->sede?->nombre,
            'puede_editar' => $this->progreso->puedeEditarBloque($user, $b),
        ])->values()]);
    }

    public function bloque(Request $request, Bloque $bloque): JsonResponse
    {
        abort_unless($this->progreso->puedeVerBloque($request->user(), $bloque), 403);
        $m = $this->progreso->matriz($bloque, $request->integer('anio') ?: null);

        return response()->json([
            'bloque' => ['id' => $bloque->id, 'nombre' => $bloque->nombre, 'anio' => $bloque->año ? (int) $bloque->año : null, 'sede' => $bloque->sede?->nombre],
            'anio' => $m['anio'],
            'puede_editar' => $this->progreso->puedeEditarBloque($request->user(), $bloque),
            'estados' => ProgresoToque::ESTADOS,
            'resultados' => EvaluacionToque::RESULTADOS,
            'toques' => $m['toques']->map(fn (ProgramaRitmo $t) => ['id' => $t->id, 'nombre' => $t->nombre, 'slug' => $t->slug, 'opcional' => (bool) $t->opcional])->values(),
            'alumnos' => $m['alumnos']->map(fn (Alumno $a) => [
                'id' => $a->id,
                'nombre' => $a->nombre_apellido,
                'anio_actual' => $m['anios'][$a->id] ?? $m['anio'],
                'obligatorios_tocados' => $m['resumen'][$a->id]['aprobados'],
                'obligatorios' => $m['resumen'][$a->id]['obligatorios'],
                'estados' => (object) collect($m['estados'][$a->id] ?? [])->map(fn ($e) => $e['estado'])->all(),
            ])->values(),
            'propuestas' => $m['propuestas']->map(fn (PromocionAnio $p) => $this->propuesta($p))->values(),
        ]);
    }

    /** Edición rápida de una celda. */
    public function estado(Request $request, Bloque $bloque): JsonResponse
    {
        $d = $request->validate($this->progreso->reglasEstado());
        $alumno = Alumno::query()->findOrFail($d['alumno_id']);
        $toque = ProgramaRitmo::query()->findOrFail($d['programa_ritmo_id'], ['id', 'año', 'nombre', 'opcional']);
        $fila = $this->progreso->actualizarEstado($request->user(), $bloque, $alumno, $toque, $d['estado'], $d['nota'] ?? null);
        $propuesta = PromocionAnio::query()->where('alumno_id', $alumno->id)->where('estado', PromocionAnio::PROPUESTA)->first();

        return response()->json([
            'data' => ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $toque->id, 'estado' => $fila->estado, 'fecha' => $fila->fecha?->toDateString()],
            'propuesta' => $propuesta ? $this->propuesta($propuesta) : null,
        ]);
    }

    public function evaluacion(Request $request): JsonResponse
    {
        $d = $request->validate($this->progreso->reglasEvaluacion());
        $e = $this->progreso->registrarEvaluacion($request->user(), $d);

        return response()->json(['data' => ['id' => $e->id, 'resultado' => $e->resultado]], 201);
    }

    public function confirmar(Request $request, PromocionAnio $promocion): JsonResponse
    {
        $p = $this->progreso->confirmarPromocion($request->user(), $promocion, $request->input('nota'));

        return response()->json(['data' => $this->propuesta($p), 'mensaje' => "Pasa a {$p->anio_hasta}° año."]);
    }

    public function descartar(Request $request, PromocionAnio $promocion): JsonResponse
    {
        $p = $this->progreso->descartarPromocion($request->user(), $promocion, $request->input('nota'));

        return response()->json(['data' => $this->propuesta($p)]);
    }

    public function alumno(Request $request, Alumno $alumno): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeSobreAlumno('seguimiento.view', $alumno), 403);

        return response()->json($this->progreso->delAlumno($alumno, false) + ['puede_resolver' => $this->progreso->puedeResolver($request->user(), $alumno)]);
    }

    /** «Mi progreso»: el del propio alumno (o de cada perfil de alumno de la cuenta). */
    public function mio(Request $request): JsonResponse
    {
        $user = $request->user();
        $alumnos = Alumno::query()->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))->get();

        return response()->json(['data' => $alumnos->map(fn (Alumno $a) => $this->progreso->delAlumno($a, true))->values()]);
    }

    public function reporte(Request $request): JsonResponse
    {
        return response()->json(['data' => collect($this->progreso->reporte($request->user(), $request->integer('sede_id') ?: null))->map(fn ($f) => [
            'bloque' => ['id' => $f['bloque']->id, 'nombre' => $f['bloque']->nombre],
            'sede' => $f['bloque']->sede?->nombre,
        ] + collect($f)->except('bloque')->all())->values()]);
    }

    /** @return array<string, mixed> */
    private function propuesta(PromocionAnio $p): array
    {
        return [
            'id' => $p->id,
            'alumno_id' => $p->alumno_id,
            'alumno' => $p->alumno?->nombre_apellido,
            'anio_desde' => $p->anio_desde,
            'anio_hasta' => $p->anio_hasta,
            'estado' => $p->estado,
        ];
    }
}
