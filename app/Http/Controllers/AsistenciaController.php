<?php

namespace App\Http\Controllers;

use App\Domain\Asistencias\AsistenciaService;
use App\Domain\Asistencias\VistaAsistenciaService;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AsistenciaController extends Controller
{
    public function index(Request $request, VistaAsistenciaService $vista)
    {
        /** @var User|null $user */
        $user = auth()->user();
        $bloques = $vista->bloquesVisibles($user);
        $tiposAsistencia = Asistencia::tiposEditables();

        if ($request->get('vista') === 'lista') {
            $bloqueId = $request->filled('bloque_id') ? (int) $request->bloque_id : null;
            if ($bloqueId !== null && $user && ! $user->puedeAccederBloque($bloqueId)) {
                abort(403);
            }
            $asistencias = $vista->listado($user, $bloqueId, $request->filled('fecha') ? $request->fecha : null);

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
        if (! $bloqueId) {
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

        if ($user && ! $user->puedeAccederBloque((int) $bloque->id)) {
            abort(403);
        }

        return view('asistencias.index', [
            'bloques' => $bloques,
            'tiposAsistencia' => $tiposAsistencia,
            'vistaLista' => false,
            'matrix' => true,
            'mes' => $mes,
            'año' => $año,
            'bloque' => $bloque,
            ...$vista->matriz($bloque, $año, $mes),
            'matrixUpdateRoute' => ($user && $user->isProfesor() && ! $user->isAdmin())
                ? 'profesor.asistencias.matrix.update'
                : 'asistencias.matrix.update',
        ]);
    }

    public function matrixUpdate(Request $request, VistaAsistenciaService $vista)
    {
        if (! Schema::hasTable('asistencias') || ! Schema::hasTable('alumnos') || ! Schema::hasTable('bloques')) {
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
            'cells.*.*' => Asistencia::reglaValidacionTipo(),
        ]);

        $bloque = Bloque::with('horarios')->findOrFail($validated['bloque_id']);

        /** @var User|null $user */
        $user = auth()->user();
        if ($user && ! $user->puedeAccederBloque((int) $bloque->id)) {
            abort(403);
        }

        $vista->guardarPlanilla($bloque, (int) $validated['año'], (int) $validated['mes'], $validated['cells'] ?? []);

        return redirect()->route(
            ($user && $user->isProfesor() && ! $user->isAdmin())
                ? 'profesor.asistencias.matrix'
                : 'asistencias.index',
            [
                'bloque_id' => $bloque->id,
                'mes' => $validated['mes'],
                'año' => $validated['año'],
            ]
        )->with('success', 'Matriz de asistencias guardada.');
    }

    public function create(Request $request, VistaAsistenciaService $vista)
    {
        $bloqueId = $request->get('bloque_id');
        /** @var User|null $user */
        $user = auth()->user();
        $bloques = $vista->bloquesVisibles($user, ordenarPorNombre: false);

        // Con un solo bloque no hay nada que elegir: se abre directo la lista de hoy.
        if (! $bloqueId && $bloques->count() === 1) {
            return redirect()->to($request->url().'?'.http_build_query([
                'bloque_id' => $bloques->first()->id,
                'fecha' => $request->get('fecha', date('Y-m-d')),
            ]));
        }

        $tiposAsistencia = Asistencia::tiposEditables();

        if ($bloqueId) {
            $bloque = Bloque::with(['alumnos', 'sede'])->findOrFail($bloqueId);
            if ($user && ! $user->puedeAccederBloque((int) $bloque->id)) {
                abort(403);
            }
            $asistenciasHoy = $vista->asistenciasDelDia($bloque, $request->get('fecha', date('Y-m-d')));

            return view('asistencias.create', compact('bloque', 'bloques', 'tiposAsistencia', 'asistenciasHoy'));
        }

        return view('asistencias.create', compact('bloques', 'tiposAsistencia'));
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('asistencias') || ! Schema::hasTable('alumnos') || ! Schema::hasTable('bloques')) {
            return back()->withErrors([
                'general' => 'Faltan tablas requeridas para registrar asistencias. Ejecutá migraciones y reintentá.',
            ])->withInput();
        }

        // Autorizar antes de validar: no revelar nada de bloques ajenos.
        $bloque = Bloque::query()->findOrFail($request->integer('bloque_id'));
        $this->authorize('tomarAsistencia', $bloque);

        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'fecha' => 'required|date',
            'asistencias' => 'required|array',
            'asistencias.*.alumno_id' => 'required|exists:alumnos,id',
            'asistencias.*.presente' => 'boolean',
            'asistencias.*.tipo_asistencia' => Asistencia::reglaValidacionTipo(),
        ]);

        $fecha = $validated['fecha'];
        $bloqueId = $validated['bloque_id'];

        $registros = [];
        foreach ($validated['asistencias'] as $asistenciaData) {
            $registros[(int) $asistenciaData['alumno_id']] = $asistenciaData['tipo_asistencia']
                ?? (! empty($asistenciaData['presente']) ? 'presente' : 'ausencia_injustificada');
        }
        app(AsistenciaService::class)->registrar($bloque, $fecha, $registros, $request->user());

        $f = Carbon::parse($fecha);
        /** @var User|null $user */
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
        $this->autorizarAsistencia($asistencia);
        $asistencia->load(['alumno', 'bloque']);

        return view('asistencias.show', compact('asistencia'));
    }

    public function edit(Asistencia $asistencia)
    {
        $this->autorizarAsistencia($asistencia);
        $asistencia->load(['alumno', 'bloque']);
        $tiposAsistencia = Asistencia::tiposEditables();

        return view('asistencias.edit', compact('asistencia', 'tiposAsistencia'));
    }

    public function update(Request $request, Asistencia $asistencia)
    {
        $this->autorizarAsistencia($asistencia);
        $validated = $request->validate([
            'presente' => 'boolean',
            'tipo_asistencia' => Asistencia::reglaValidacionTipo(),
        ]);

        if (isset($validated['tipo_asistencia'])) {
            $asistencia->tipo_asistencia = $validated['tipo_asistencia'];
            $asistencia->presente = Asistencia::esPresente($validated['tipo_asistencia']);
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
        $this->autorizarAsistencia($asistencia);
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

    private function autorizarAsistencia(Asistencia $asistencia): void
    {
        $user = auth()->user();
        if ($user && ! $user->puedeAccederBloque((int) $asistencia->bloque_id)) {
            abort(403);
        }
    }
}
