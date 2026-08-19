<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\ObservacionPedagogica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SeguimientoPedagogicoController extends Controller
{
    public function store(Request $request)
    {
        if (! Schema::hasTable('observaciones_pedagogicas')) {
            return back()->withErrors(['cuerpo' => 'Falta migrar la bitácora pedagógica.'])->withInput();
        }

        $validated = $request->validate([
            'alumno_id' => 'required|exists:alumnos,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'fecha' => 'required|date',
            'tipo' => ['required', Rule::in(array_keys(ObservacionPedagogica::TIPOS))],
            'eje' => ['nullable', Rule::in(array_keys(ObservacionPedagogica::EJES))],
            'toque' => 'nullable|string|max:160',
            'cuerpo' => 'required|string|max:4000',
            'proximo_paso' => 'nullable|string|max:400',
            'visible_alumno' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $alumno = Alumno::query()->findOrFail($validated['alumno_id']);
        $alumno->loadMissing(['bloques', 'bloque']);
        $this->autorizarAlumno($user, $alumno);

        if (! empty($validated['bloque_id']) && $user && ! $user->puedeAccederBloque((int) $validated['bloque_id'])) {
            abort(403);
        }

        $fila = [
            'alumno_id' => $alumno->id,
            'user_id' => $user->id,
            'bloque_id' => $validated['bloque_id'] ?? null,
            'fecha' => $validated['fecha'],
            'tipo' => $validated['tipo'],
            'toque' => $validated['toque'] ?? null,
            'cuerpo' => trim($validated['cuerpo']),
        ];
        if (Schema::hasColumn('observaciones_pedagogicas', 'eje')) {
            $fila['eje'] = $validated['eje'] ?? null;
            $fila['proximo_paso'] = isset($validated['proximo_paso']) ? trim((string) $validated['proximo_paso']) : null;
            $fila['visible_alumno'] = $request->boolean('visible_alumno');
        }
        ObservacionPedagogica::create($fila);

        return back()->with('success', 'Quedó registrada la nota pedagógica.');
    }

    public function destroy(ObservacionPedagogica $observacionPedagogica)
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->isAdmin() && (int) $observacionPedagogica->user_id !== (int) $user->id) {
            abort(403);
        }
        $this->autorizarAlumno($user, $observacionPedagogica->alumno);
        $observacionPedagogica->delete();

        return back()->with('success', 'Nota pedagógica eliminada.');
    }

    private function autorizarAlumno($user, Alumno $alumno): void
    {
        if (! $user) {
            abort(403);
        }
        $alumno->loadMissing(['bloques', 'bloque']);
        if ($user->puedeGestionarAlumno($alumno)) {
            return;
        }
        abort(403);
    }
}
