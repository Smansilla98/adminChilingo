<?php

namespace App\Http\Controllers;

use App\Models\InventarioItem;
use App\Models\Sede;
use App\Models\Alumno;
use Illuminate\Http\Request;

class InventarioItemController extends Controller
{
    public function index(Request $request)
    {
        $query = InventarioItem::with(['sede', 'alumno']);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('propietario_tipo')) {
            $query->where('propietario_tipo', $request->propietario_tipo);
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', '%' . $q . '%')
                    ->orWhere('codigo', 'like', '%' . $q . '%')
                    ->orWhere('marca', 'like', '%' . $q . '%')
                    ->orWhere('modelo', 'like', '%' . $q . '%');
            });
        }

        $items = $query->orderBy('tipo')->orderBy('nombre')->paginate(25);

        $sedes = Sede::orderBy('nombre')->get();
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;

        return view('inventarios.index', compact('items', 'sedes', 'tipos', 'propietarios'));
    }

    public function create(Request $request)
    {
        $sedes = Sede::orderBy('nombre')->get();
        $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;
        $estados = InventarioItem::ESTADOS;
        $origenes = InventarioItem::ORIGENES;

        $defaults = [
            'sede_id' => $request->get('sede_id'),
            'tipo' => $request->get('tipo', 'instrumento'),
        ];

        return view('inventarios.create', compact('sedes', 'alumnos', 'tipos', 'propietarios', 'estados', 'origenes', 'defaults'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);
        InventarioItem::create($validated);
        return redirect()->route('inventarios.index')->with('success', 'Item de inventario creado.');
    }

    public function show(InventarioItem $inventario)
    {
        $inventario->load(['sede', 'alumno']);
        return view('inventarios.show', ['item' => $inventario]);
    }

    public function edit(InventarioItem $inventario)
    {
        $inventario->load(['sede', 'alumno']);
        $sedes = Sede::orderBy('nombre')->get();
        $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;
        $estados = InventarioItem::ESTADOS;
        $origenes = InventarioItem::ORIGENES;
        return view('inventarios.edit', compact('inventario', 'sedes', 'alumnos', 'tipos', 'propietarios', 'estados', 'origenes'));
    }

    public function update(Request $request, InventarioItem $inventario)
    {
        $validated = $this->validateItem($request, $inventario->id);
        $inventario->update($validated);
        return redirect()->route('inventarios.index')->with('success', 'Item actualizado.');
    }

    public function destroy(InventarioItem $inventario)
    {
        $inventario->delete();
        return redirect()->route('inventarios.index')->with('success', 'Item eliminado.');
    }

    private function validateItem(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'tipo' => 'required|string|max:30',
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:255',
            'es_consumible' => 'boolean',
            'cantidad' => 'required|numeric|min:0',
            'unidad' => 'nullable|string|max:20',
            'propietario_tipo' => 'required|in:escuela,alumno',
            'alumno_id' => 'nullable|exists:alumnos,id',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'linea' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'medida' => 'nullable|string|max:255',
            'diametro_pulgadas' => 'nullable|numeric|min:0|max:99.99',
            'torres' => 'nullable|integer|min:0|max:999',
            'anio_fabricacion' => 'nullable|integer|min:1900|max:2100',
            'origen_adquisicion' => 'nullable|in:comprado,donado,prestado,otro',
            'fecha_adquisicion' => 'nullable|date',
            'precio' => 'nullable|numeric|min:0',
            'estado' => 'required|in:nuevo,bueno,regular,reparacion,baja',
            'reparado_en' => 'nullable|date',
            'detalle_reparacion' => 'nullable|string',
            'utilitario' => 'boolean',
            'notas' => 'nullable|string',
        ]);

        $validated['es_consumible'] = $request->boolean('es_consumible');
        $validated['utilitario'] = $request->boolean('utilitario');

        if (($validated['propietario_tipo'] ?? 'escuela') !== 'alumno') {
            $validated['alumno_id'] = null;
        }

        if (!$validated['es_consumible']) {
            $validated['cantidad'] = 1;
            $validated['unidad'] = $validated['unidad'] ?: 'u';
        }

        return $validated;
    }
}

