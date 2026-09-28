<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de la auditoría (solo lectura). La registran los modelos y servicios del
 * backend; la app no genera registros propios. Mismo permiso que el panel web.
 */
class AuditoriaController extends Controller
{
    public const ACCIONES = [
        'created' => 'Alta',
        'updated' => 'Modificación',
        'deleted' => 'Baja',
        'fusionada' => 'Fusión',
    ];

    public function index(Request $request): JsonResponse
    {
        $pagina = Auditoria::query()
            ->with('user:id,name,username')
            ->when($request->filled('entidad'), fn ($q) => $q->where('entidad_tipo', $request->input('entidad')))
            ->when($request->filled('entidad_id'), fn ($q) => $q->where('entidad_id', $request->integer('entidad_id')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->input('accion')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('origen'), fn ($q) => $q->where('origen', $request->input('origen')))
            ->when($request->filled('desde'), fn ($q) => $q->where('created_at', '>=', $request->date('desde')->startOfDay()))
            ->when($request->filled('hasta'), fn ($q) => $q->where('created_at', '<=', $request->date('hasta')->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.trim((string) $request->input('q')).'%';
                $q->where(fn ($w) => $w->where('entidad_tipo', 'like', $t)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $t)->orWhere('username', 'like', $t)));
            })
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('por_pagina', 50), 10), 100));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Auditoria $a) => $this->registro($a, false))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Auditoria $auditoria): JsonResponse
    {
        return response()->json(['data' => $this->registro($auditoria->load('user:id,name,username'), true)]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'entidades' => Auditoria::query()->distinct()->orderBy('entidad_tipo')->pluck('entidad_tipo')->values(),
            'acciones' => self::ACCIONES,
            'origenes' => ['web' => 'Panel web', 'api' => 'App / API', 'cli' => 'Sistema'],
        ]);
    }

    /** @return array<string, mixed> */
    private function registro(Auditoria $a, bool $conDatos): array
    {
        $antes = $a->datos_anteriores ?? [];
        $despues = $a->datos_nuevos ?? [];

        return [
            'id' => $a->id,
            'accion' => $a->accion,
            'accion_nombre' => self::ACCIONES[$a->accion] ?? $a->accion,
            'entidad' => $a->entidad_tipo,
            'entidad_id' => $a->entidad_id,
            'usuario' => $a->user ? ['id' => $a->user->id, 'nombre' => $a->user->name, 'username' => $a->user->username] : null,
            'origen' => $a->origen,
            'fecha' => $a->created_at?->toIso8601String(),
            'campos' => array_values(array_unique(array_merge(array_keys($antes), array_keys($despues)))),
            'antes' => $conDatos ? $antes : null,
            'despues' => $conDatos ? $despues : null,
            'ip' => $conDatos ? $a->ip : null,
        ];
    }
}
