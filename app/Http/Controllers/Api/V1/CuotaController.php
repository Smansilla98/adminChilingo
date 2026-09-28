<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Finanzas\CuotaService;
use App\Http\Controllers\Controller;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Sede;
use App\Models\WhatsappMensaje;
use App\Services\AmbitoSedeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Cuotas: listado del alcance, ficha (pagos registrados, alumnos asignados,
 * recordatorios), alta, edición, activar/desactivar y baja segura. Mismas reglas que
 * el panel web (CuotaService + CuotaPolicy). La API usa `anio` en lugar de `año`.
 */
class CuotaController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        $this->authorize('viewAny', Cuota::class);
        $query = Cuota::query()->with(['bloque:id,nombre,sede_id', 'sede:id,nombre'])->withCount('pagoDetalles');
        $filtro = $ambito->idsPara($request->user(), 'cuotas.view');
        if ($filtro !== null) {
            $ambito->aplicarCuotas($query, $filtro);
        }
        $query->where('año', $request->integer('anio') ?: (int) now()->year);
        if ($request->filled('bloque_id')) {
            // Igual que la web: las del bloque, las generales y las de la sede del bloque.
            $bid = $request->integer('bloque_id');
            $sedeDelBloque = Bloque::query()->whereKey($bid)->value('sede_id');
            $query->where(function ($q) use ($bid, $sedeDelBloque) {
                $q->where('bloque_id', $bid)->orWhere('alcance', Cuota::ALCANCE_GENERAL);
                if ($sedeDelBloque) {
                    $q->orWhere(fn ($s) => $s->where('alcance', Cuota::ALCANCE_SEDE)->where('sede_id', $sedeDelBloque));
                }
            });
        }
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }
        if ($request->filled('alcance')) {
            $query->where('alcance', $request->input('alcance'));
        }
        if ($request->filled('mes')) {
            $query->where('mes', $request->integer('mes'));
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        if ($request->filled('q')) {
            $query->where('nombre', 'like', '%'.trim((string) $request->input('q')).'%');
        }
        $pagina = $query->orderBy('mes')->orderBy('id')->paginate(min(max($request->integer('por_pagina', 50), 10), 200));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Cuota $c) => $this->resumen($c))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Cuota $cuota): JsonResponse
    {
        $this->authorize('view', $cuota);
        $cuota->loadCount('pagoDetalles')->load(['bloque:id,nombre,sede_id', 'sede:id,nombre', 'alumnos:id,nombre_apellido']);
        $user = $request->user();

        $pagos = PagoDetalle::query()->where('cuota_id', $cuota->id)
            ->with(['alumno:id,nombre_apellido,persona_id', 'pago:id,fecha_pago,anulado_at'])
            ->orderByDesc('id')->limit(200)->get();
        $recordatorios = Schema::hasTable('whatsapp_mensajes')
            ? WhatsappMensaje::query()->where('cuota_id', $cuota->id)->where('tipo', WhatsappMensaje::TIPO_CUOTA)
                ->with('alumno:id,nombre_apellido')->orderByDesc('id')->get()->unique('alumno_id')->values()
            : collect();

        return response()->json(['data' => $this->resumen($cuota) + [
            'descripcion' => $cuota->descripcion,
            'alumnos' => $cuota->alumnos->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre_apellido])->values(),
            'cobros' => $pagos->map(fn (PagoDetalle $d) => [
                'pago_id' => $d->pago_id,
                'alumno' => $d->alumno ? ['id' => $d->alumno->id, 'nombre' => $d->alumno->nombre_apellido, 'persona_id' => $d->alumno->persona_id] : null,
                'monto' => (float) $d->monto,
                'fecha' => $d->pago?->fecha_pago?->toDateString(),
                'anulado' => $d->pago?->anulado_at !== null,
            ])->values(),
            'total_cobrado' => round((float) $pagos->filter(fn ($d) => $d->pago?->anulado_at === null)->sum('monto'), 2),
            'recordatorios' => $recordatorios->map(fn ($m) => [
                'alumno' => $m->alumno?->nombre_apellido,
                'estado' => $m->status,
                'fecha' => $m->created_at?->toIso8601String(),
            ])->values(),
            'acciones' => [
                'editar' => $user->can('update', $cuota),
                'eliminar' => $user->can('delete', $cuota),
                'registrar_pago' => $user->acceso()->puede('pagos.create'),
            ],
        ]]);
    }

    public function store(Request $request, CuotaService $cuotas): JsonResponse
    {
        $this->authorize('create', Cuota::class);
        $datos = $this->validar($request, $cuotas);
        $datos['activo'] = $request->boolean('activo', true);
        $cuota = $cuotas->guardar(null, $datos, $request->user());

        return $this->show($request, $cuota)->setStatusCode(201);
    }

    public function update(Request $request, Cuota $cuota, CuotaService $cuotas): JsonResponse
    {
        $this->authorize('update', $cuota);
        $datos = $this->validar($request, $cuotas);
        $datos['activo'] = $request->boolean('activo', (bool) $cuota->activo);
        if (! $request->exists('alumno_ids')) {
            $datos['alumno_ids'] = $cuota->alumnos()->pluck('alumnos.id')->all();
        }
        $cuotas->guardar($cuota, $datos, $request->user());

        return $this->show($request, $cuota->fresh());
    }

    public function destroy(Cuota $cuota, EliminacionSegura $eliminacion): JsonResponse
    {
        $this->authorize('delete', $cuota);
        $eliminacion->verificar($cuota);
        $cuota->delete();

        return response()->json(['ok' => true]);
    }

    /** Opciones del formulario según dónde puede definir cuotas. */
    public function catalogo(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['cuotas.create', 'cuotas.update']), 403);
        $filtro = $ambito->idsPara($request->user(), 'cuotas.view');
        $bloques = Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre');
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre');
        if ($filtro !== null) {
            $ambito->aplicarBloques($bloques, $filtro);
            $ambito->aplicarSedesCatalogo($sedes, $filtro);
        }

        return response()->json([
            'alcances' => CuotaService::ALCANCES,
            'puede_general' => $request->user()->acceso()->puedeGlobal('cuotas.create'),
            'sedes' => $sedes->get(['id', 'nombre']),
            'bloques' => $bloques->get()->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, CuotaService $cuotas): array
    {
        $reglas = $cuotas->reglas($request->input('alcance'));
        $reglas['anio'] = $reglas['año'];
        unset($reglas['año']);
        $datos = $request->validate($reglas);
        $datos['año'] = $datos['anio'];
        unset($datos['anio']);

        return $datos;
    }

    /** @return array<string, mixed> */
    private function resumen(Cuota $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'anio' => (int) $c->año,
            'mes' => $c->mes,
            'periodo' => $c->mes ? sprintf('%02d/%d', $c->mes, $c->año) : (string) $c->año,
            'monto' => (float) $c->monto,
            'vencimiento' => $c->fecha_vencimiento?->toDateString(),
            'vencida' => $c->fecha_vencimiento !== null && $c->fecha_vencimiento->lt(today()),
            'alcance' => $c->alcanceNormalizado(),
            'bloque' => $c->bloque?->nombre,
            'bloque_id' => $c->bloque_id,
            'sede' => $c->sede?->nombre,
            'sede_id' => $c->sede_id,
            'pagos' => $c->pago_detalles_count,
            'activo' => (bool) $c->activo,
        ];
    }
}
