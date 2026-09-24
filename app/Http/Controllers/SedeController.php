<?php

namespace App\Http\Controllers;

use App\Domain\Datos\EliminacionSegura;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SedeController extends Controller
{
    public function index()
    {
        $query = Sede::orderBy('nombre');
        auth()->user()->acceso()->alcance('sedes.view')->aplicarPorSede($query, 'id');
        $sedes = $query->paginate(20);

        return view('sedes.index', compact('sedes'));
    }

    public function create()
    {
        $this->authorize('create', Sede::class);

        return view('sedes.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Sede::class);
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:sedes,nombre',
            'direccion' => 'nullable|string|max:255',
            'tipo_propiedad' => 'nullable|string|in:propia,alquilada,compartida,otro',
            'costo_alquiler_mensual' => 'nullable|numeric|min:0',
            'liquidacion_retencion_escuela' => 'nullable|numeric|min:0',
            'liquidacion_porc_docente' => 'nullable|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        if (empty($validated['tipo_propiedad'])) {
            $validated['tipo_propiedad'] = 'alquilada';
        }
        if (($validated['liquidacion_retencion_escuela'] ?? null) === null || $validated['liquidacion_retencion_escuela'] === '') {
            $validated['liquidacion_retencion_escuela'] = 0;
        }
        if (($validated['liquidacion_porc_docente'] ?? null) === null || $validated['liquidacion_porc_docente'] === '') {
            $validated['liquidacion_porc_docente'] = 40;
        }
        $validated = $this->filterSedeColumns($validated);

        Sede::create($validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede creada exitosamente.');
    }

    public function show(Sede $sede)
    {
        $this->authorize('view', $sede);
        $sede->load(['bloques.profesor', 'alumnos', 'eventos']);

        return view('sedes.show', compact('sede'));
    }

    public function edit(Sede $sede)
    {
        $this->authorize('update', $sede);

        return view('sedes.edit', compact('sede'));
    }

    public function update(Request $request, Sede $sede)
    {
        $this->authorize('update', $sede);
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:sedes,nombre,'.$sede->id,
            'direccion' => 'nullable|string|max:255',
            'tipo_propiedad' => 'nullable|string|in:propia,alquilada,compartida,otro',
            'costo_alquiler_mensual' => 'nullable|numeric|min:0',
            'liquidacion_retencion_escuela' => 'nullable|numeric|min:0',
            'liquidacion_porc_docente' => 'nullable|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->has('activo') ? true : false;
        if (empty($validated['tipo_propiedad'])) {
            $validated['tipo_propiedad'] = 'alquilada';
        }
        if (($validated['liquidacion_retencion_escuela'] ?? null) === null || $validated['liquidacion_retencion_escuela'] === '') {
            $validated['liquidacion_retencion_escuela'] = 0;
        }
        if (($validated['liquidacion_porc_docente'] ?? null) === null || $validated['liquidacion_porc_docente'] === '') {
            $validated['liquidacion_porc_docente'] = 40;
        }
        $validated = $this->filterSedeColumns($validated);

        $sede->update($validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede actualizada exitosamente.');
    }

    public function destroy(Sede $sede, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $sede);
        $eliminacion->verificar($sede);
        $sede->delete();

        return redirect()->route('sedes.index')
            ->with('success', 'Sede eliminada exitosamente.');
    }

    /**
     * Solo incluir en el array los campos que existen en la tabla sedes
     * (tipo_propiedad y costo_alquiler_mensual se agregan en una migración posterior).
     */
    private function filterSedeColumns(array $validated): array
    {
        $allowed = ['nombre', 'direccion', 'activo'];
        if (Schema::hasTable('sedes')) {
            if (Schema::hasColumn('sedes', 'tipo_propiedad')) {
                $allowed[] = 'tipo_propiedad';
            }
            if (Schema::hasColumn('sedes', 'costo_alquiler_mensual')) {
                $allowed[] = 'costo_alquiler_mensual';
            }
            if (Schema::hasColumn('sedes', 'liquidacion_retencion_escuela')) {
                $allowed[] = 'liquidacion_retencion_escuela';
            }
            if (Schema::hasColumn('sedes', 'liquidacion_porc_docente')) {
                $allowed[] = 'liquidacion_porc_docente';
            }
        }

        return array_intersect_key($validated, array_flip($allowed));
    }
}
