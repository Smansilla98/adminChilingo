<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Bloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    /** Tipos de asistencia para formularios (solo los 4 del Excel, sin legacy) */
    protected function getTiposAsistencia(): array
    {
        $todos = Asistencia::TIPOS_ASISTENCIA;

        return array_diff_key($todos, array_flip(['ausente', 'justificado']));
    }

    /**
     * Fechas de clase en el mes según horarios del bloque (día ISO 1–7).
     * Si el bloque no tiene horarios, se asume viernes (como el Excel Trinchera).
     *
     * @return Collection<int, Carbon>
     */
    protected function fechasClaseDelMes(Bloque $bloque, int $año, int $mes): Collection
    {
        $bloque->loadMissing('horarios');
        $dias = $bloque->horarios->pluck('dia_semana')->unique()->sort()->values();
        if ($dias->isEmpty()) {
            $dias = collect([5]);
        }

        $start = Carbon::createFromDate($año, $mes, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $out = collect();
        $d = $start->copy();
        while ($d->lte($end)) {
            if ($dias->contains($d->dayOfWeekIso)) {
                $out->push($d->copy());
            }
            $d->addDay();
        }

        return $out;
    }

    public function index(Request $request)
    {
        $bloquesQuery = Bloque::where('activo', true)->with('sede')->orderBy('nombre');
        /** @var \App\Models\User|null $userIdx */
        $userIdx = auth()->user();
        if ($userIdx && $userIdx->isProfesor() && ! $userIdx->isAdmin()) {
            $profIdx = $userIdx->profesor;
            $idsIdx = $profIdx ? $profIdx->bloqueIdsDondeParticipa()->all() : [];
            $bloquesQuery->whereIn('id', $idsIdx !== [] ? $idsIdx : [0]);
        }
        $bloques = $bloquesQuery->get();
        $tiposAsistencia = $this->getTiposAsistencia();

        if ($request->get('vista') === 'lista') {
            $query = Asistencia::with(['alumno', 'bloque']);

            if ($request->filled('bloque_id')) {
                $query->where('bloque_id', $request->bloque_id);
            }

            if ($request->filled('fecha')) {
                $query->where('fecha', $request->fecha);
            }

            $asistencias = $query->orderBy('fecha', 'desc')->paginate(50);

            return view('asistencias.index', [
                'bloques' => $bloques,
                'tiposAsistencia' => $tiposAsistencia,
                'vistaLista' => true,
                'asistencias' => $asistencias,
                'matrix' => null,
                'mes' => (int) $request->input('mes', now()->month),
                'año' => (int) $request->input('año', now()->year),
            ]);
        }

        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $año = (int) $request->input('año', now()->year);
        if ($año < 2000 || $año > 2100) {
            $año = (int) now()->year;
        }

        $bloqueId = $request->filled('bloque_id') ? (int) $request->bloque_id : null;
        if (!$bloqueId) {
            return view('asistencias.index', [
                'bloques' => $bloques,
                'tiposAsistencia' => $tiposAsistencia,
                'vistaLista' => false,
                'matrix' => null,
                'mes' => $mes,
                'año' => $año,
                'bloque' => null,
                'fechas' => collect(),
                'alumnos' => collect(),
                'asistenciasMap' => collect(),
            ]);
        }

        $bloque = Bloque::with(['horarios', 'sede', 'profesor'])
            ->where('activo', true)
            ->findOrFail($bloqueId);

        if ($userIdx && $userIdx->isProfesor() && ! $userIdx->isAdmin()) {
            $profIdx = $userIdx->profesor;
            $permitidosIdx = $profIdx ? $profIdx->bloqueIdsDondeParticipa()->all() : [];
            if (! in_array((int) $bloque->id, array_map('intval', $permitidosIdx), true)) {
                abort(403);
            }
        }

        $fechas = $this->fechasClaseDelMes($bloque, $año, $mes);
        $alumnos = $bloque->alumnos()->where('alumnos.activo', true)->orderBy('alumnos.nombre_apellido')->get();

        $asistenciasMap = collect();
        if (Schema::hasTable('asistencias')) {
            $asistenciasMap = Asistencia::query()
                ->where('bloque_id', $bloque->id)
                ->whereYear('fecha', $año)
                ->whereMonth('fecha', $mes)
                ->get()
                ->keyBy(fn (Asistencia $a) => $a->alumno_id.'|'.$a->fecha->format('Y-m-d'));
        }

        return view('asistencias.index', [
            'bloques' => $bloques,
            'tiposAsistencia' => $tiposAsistencia,
            'vistaLista' => false,
            'matrix' => true,
            'mes' => $mes,
            'año' => $año,
            'bloque' => $bloque,
            'fechas' => $fechas,
            'alumnos' => $alumnos,
            'asistenciasMap' => $asistenciasMap,
        ]);
    }

    public function matrixUpdate(Request $request)
    {
        if (!Schema::hasTable('asistencias') || !Schema::hasTable('alumnos') || !Schema::hasTable('bloques')) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar asistencias. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'mes' => 'required|integer|min:1|max:12',
            'año' => 'required|integer|min:2000|max:2100',
            'cells' => 'nullable|array',
            'cells.*' => 'array',
            'cells.*.*' => 'nullable|string|in:presente,tarde,ausencia_justificada,ausencia_injustificada',
        ]);

        $bloque = Bloque::with('horarios')->findOrFail($validated['bloque_id']);

        /** @var \App\Models\User|null $userMx */
        $userMx = auth()->user();
        if ($userMx && $userMx->isProfesor() && ! $userMx->isAdmin()) {
            $profMx = $userMx->profesor;
            $permitidosMx = $profMx ? $profMx->bloqueIdsDondeParticipa()->all() : [];
            if (! in_array((int) $bloque->id, array_map('intval', $permitidosMx), true)) {
                abort(403);
            }
        }

        $fechasPermitidas = $this->fechasClaseDelMes($bloque, (int) $validated['año'], (int) $validated['mes'])
            ->map(fn (Carbon $c) => $c->format('Y-m-d'))
            ->flip();

        $alumnoIdsPermitidos = $bloque->alumnos()->where('alumnos.activo', true)->pluck('alumnos.id')->flip();

        $cells = $validated['cells'] ?? [];

        DB::beginTransaction();
        try {
            foreach ($cells as $alumnoIdStr => $porFecha) {
                $alumnoId = (int) $alumnoIdStr;
                if (!$alumnoIdsPermitidos->has($alumnoId)) {
                    continue;
                }
                foreach ($porFecha as $fechaStr => $tipo) {
                    if (!$fechasPermitidas->has($fechaStr)) {
                        continue;
                    }
                    if ($tipo === null || $tipo === '') {
                        Asistencia::query()
                            ->where('bloque_id', $bloque->id)
                            ->where('alumno_id', $alumnoId)
                            ->whereDate('fecha', $fechaStr)
                            ->delete();
                        continue;
                    }

                    Asistencia::updateOrCreate(
                        [
                            'alumno_id' => $alumnoId,
                            'bloque_id' => $bloque->id,
                            'fecha' => $fechaStr,
                        ],
                        [
                            'tipo_asistencia' => $tipo,
                            'presente' => in_array($tipo, ['presente', 'tarde'], true),
                        ]
                    );
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('asistencias.index', [
            'bloque_id' => $bloque->id,
            'mes' => $validated['mes'],
            'año' => $validated['año'],
        ])->with('success', 'Matriz de asistencias guardada.');
    }

    public function create(Request $request)
    {
        $bloqueId = $request->get('bloque_id');
        $bloquesQuery = Bloque::where('activo', true)->with('sede');
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->isProfesor() && ! $user->isAdmin()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $bloquesQuery->whereIn('id', $ids !== [] ? $ids : [0]);
        }
        $bloques = $bloquesQuery->get();

        if ($bloqueId) {
            $bloque = Bloque::with('alumnos')->findOrFail($bloqueId);
            /** @var \App\Models\User|null $userBloque */
            $userBloque = auth()->user();
            if ($userBloque && $userBloque->isProfesor() && ! $userBloque->isAdmin()) {
                $profB = $userBloque->profesor;
                $permitidos = $profB ? $profB->bloqueIdsDondeParticipa()->all() : [];
                if (! in_array((int) $bloque->id, array_map('intval', $permitidos), true)) {
                    abort(403);
                }
            }
            $tiposAsistencia = $this->getTiposAsistencia();

            return view('asistencias.create', compact('bloque', 'bloques', 'tiposAsistencia'));
        }

        $tiposAsistencia = $this->getTiposAsistencia();

        return view('asistencias.create', compact('bloques', 'tiposAsistencia'));
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('asistencias') || !Schema::hasTable('alumnos') || !Schema::hasTable('bloques')) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar asistencias. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'fecha' => 'required|date',
            'asistencias' => 'required|array',
            'asistencias.*.alumno_id' => 'required|exists:alumnos,id',
            'asistencias.*.presente' => 'boolean',
            'asistencias.*.tipo_asistencia' => 'nullable|string|in:presente,tarde,ausencia_justificada,ausencia_injustificada',
        ]);

        /** @var \App\Models\User|null $userStore */
        $userStore = auth()->user();
        if ($userStore && $userStore->isProfesor() && ! $userStore->isAdmin()) {
            $profS = $userStore->profesor;
            $permitidosS = $profS ? $profS->bloqueIdsDondeParticipa()->all() : [];
            if (! in_array((int) $validated['bloque_id'], array_map('intval', $permitidosS), true)) {
                abort(403);
            }
        }

        $fecha = $validated['fecha'];
        $bloqueId = $validated['bloque_id'];

        foreach ($validated['asistencias'] as $asistenciaData) {
            $tipo = $asistenciaData['tipo_asistencia'] ?? (isset($asistenciaData['presente']) && $asistenciaData['presente'] ? 'presente' : 'ausencia_injustificada');
            Asistencia::updateOrCreate(
                [
                    'alumno_id' => $asistenciaData['alumno_id'],
                    'bloque_id' => $bloqueId,
                    'fecha' => $fecha,
                ],
                [
                    'tipo_asistencia' => $tipo,
                    'presente' => in_array($tipo, ['presente', 'tarde']),
                ]
            );
        }

        $f = Carbon::parse($fecha);
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user && $user->isAdmin()) {
            return redirect()->route('asistencias.index', [
                'bloque_id' => $bloqueId,
                'mes' => $f->month,
                'año' => $f->year,
            ])->with('success', 'Asistencias registradas exitosamente.');
        }

        return redirect()->route('profesor.asistencias.create', [
            'bloque_id' => $bloqueId,
            'fecha' => $f->format('Y-m-d'),
        ])->with('success', 'Asistencias registradas exitosamente.');
    }

    public function show(Asistencia $asistencia)
    {
        $asistencia->load(['alumno', 'bloque']);

        return view('asistencias.show', compact('asistencia'));
    }

    public function edit(Asistencia $asistencia)
    {
        $asistencia->load(['alumno', 'bloque']);
        $tiposAsistencia = $this->getTiposAsistencia();

        return view('asistencias.edit', compact('asistencia', 'tiposAsistencia'));
    }

    public function update(Request $request, Asistencia $asistencia)
    {
        $validated = $request->validate([
            'presente' => 'boolean',
            'tipo_asistencia' => 'nullable|string|in:presente,tarde,ausencia_justificada,ausencia_injustificada',
        ]);

        if (isset($validated['tipo_asistencia'])) {
            $asistencia->tipo_asistencia = $validated['tipo_asistencia'];
            $asistencia->presente = in_array($validated['tipo_asistencia'], ['presente', 'tarde']);
            $asistencia->save();
        } else {
            $asistencia->update($validated);
        }

        return redirect()->route('asistencias.index', [
            'bloque_id' => $asistencia->bloque_id,
            'mes' => $asistencia->fecha->month,
            'año' => $asistencia->fecha->year,
        ])->with('success', 'Asistencia actualizada exitosamente.');
    }

    public function destroy(Asistencia $asistencia)
    {
        $bloqueId = $asistencia->bloque_id;
        $mes = $asistencia->fecha->month;
        $año = $asistencia->fecha->year;
        $asistencia->delete();

        return redirect()->route('asistencias.index', [
            'bloque_id' => $bloqueId,
            'mes' => $mes,
            'año' => $año,
        ])->with('success', 'Asistencia eliminada exitosamente.');
    }
}
