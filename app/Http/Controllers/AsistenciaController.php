<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Alumno;
use App\Models\Bloque;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    /** Tipos de asistencia para formularios (solo los 4 del Excel, sin legacy) */
    protected function getTiposAsistencia(): array
    {
        $todos = Asistencia::TIPOS_ASISTENCIA;
        return array_diff_key($todos, array_flip(['ausente', 'justificado']));
    }

    public function index(Request $request)
    {
        $query = Asistencia::with(['alumno', 'bloque']);

        if ($request->filled('bloque_id')) {
            $query->where('bloque_id', $request->bloque_id);
        }

        if ($request->filled('fecha')) {
            $query->where('fecha', $request->fecha);
        }

        $asistencias = $query->orderBy('fecha', 'desc')->paginate(50);
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        $tiposAsistencia = $this->getTiposAsistencia();

        return view('asistencias.index', compact('asistencias', 'bloques', 'tiposAsistencia'));
    }

    public function create(Request $request)
    {
        $bloqueId = $request->get('bloque_id');
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        
        if ($bloqueId) {
            $bloque = Bloque::with('alumnos')->findOrFail($bloqueId);
            $tiposAsistencia = $this->getTiposAsistencia();
            return view('asistencias.create', compact('bloque', 'bloques', 'tiposAsistencia'));
        }

        $tiposAsistencia = $this->getTiposAsistencia();
        return view('asistencias.create', compact('bloques', 'tiposAsistencia'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'fecha' => 'required|date',
            'asistencias' => 'required|array',
            'asistencias.*.alumno_id' => 'required|exists:alumnos,id',
            'asistencias.*.presente' => 'boolean',
            'asistencias.*.tipo_asistencia' => 'nullable|string|in:presente,tarde,ausencia_justificada,ausencia_injustificada',
        ]);

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

        return redirect()->route('asistencias.index')
            ->with('success', 'Asistencias registradas exitosamente.');
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

        return redirect()->route('asistencias.index')
            ->with('success', 'Asistencia actualizada exitosamente.');
    }

    public function destroy(Asistencia $asistencia)
    {
        $asistencia->delete();

        return redirect()->route('asistencias.index')
            ->with('success', 'Asistencia eliminada exitosamente.');
    }
}
