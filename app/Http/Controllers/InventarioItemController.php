<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\InventarioItem;
use App\Models\InventarioMovimiento;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InventarioItemController extends Controller
{
    public function index(Request $request)
    {
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;

        // Flujo escanear / ir a código → ficha
        if ($request->filled('codigo')) {
            $codigo = trim((string) $request->input('codigo'));
            $hit = InventarioItem::query()->where('codigo', $codigo)->first()
                ?? InventarioItem::query()->where('codigo', 'like', $codigo)->orderBy('id')->first();
            if ($hit) {
                return redirect()
                    ->route('inventarios.show', $hit)
                    ->with('success', 'Ítem encontrado: '.$hit->codigo);
            }

            return redirect()
                ->route('inventarios.index', $request->except('codigo'))
                ->with('error', 'No hay ítem con código «'.$codigo.'».');
        }

        try {
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
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('q')) {
                $q = trim((string) $request->q);
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', '%'.$q.'%')
                        ->orWhere('codigo', 'like', '%'.$q.'%')
                        ->orWhere('marca', 'like', '%'.$q.'%')
                        ->orWhere('modelo', 'like', '%'.$q.'%');
                });
            }

            $items = $query->orderBy('tipo')->orderBy('nombre')->paginate(25);
            $sedes = Sede::orderBy('nombre')->get();
        } catch (QueryException $e) {
            $items = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
            $sedes = collect();
        }

        return view('inventarios.index', compact('items', 'sedes', 'tipos', 'propietarios'));
    }

    public function create(Request $request)
    {
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;
        $estados = InventarioItem::ESTADOS;
        $origenes = InventarioItem::ORIGENES;
        $defaults = [
            'sede_id' => $request->get('sede_id'),
            'tipo' => $request->get('tipo', 'instrumento'),
        ];

        try {
            $sedes = Sede::orderBy('nombre')->get();
            $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $alumnos = collect();
        }

        return view('inventarios.create', compact('sedes', 'alumnos', 'tipos', 'propietarios', 'estados', 'origenes', 'defaults'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);
        $item = InventarioItem::create($validated);
        $item->asegurarCodigo();
        $this->registrar($item, 'ingreso', 'Alta en inventario');

        return redirect()->route('inventarios.show', $item)->with('success', 'Item de inventario creado. Código '.$item->codigo.'.');
    }

    public function show(InventarioItem $inventario)
    {
        $inventario->load(['sede', 'alumno']);
        if (Schema::hasTable('inventario_movimientos')) {
            $inventario->load(['movimientos.autor', 'movimientos.sede']);
        }
        $sedes = Sede::orderBy('nombre')->get();

        return view('inventarios.show', ['item' => $inventario, 'sedes' => $sedes]);
    }

    public function edit(InventarioItem $inventario)
    {
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;
        $estados = InventarioItem::ESTADOS;
        $origenes = InventarioItem::ORIGENES;

        try {
            $inventario->load(['sede', 'alumno']);
            $sedes = Sede::orderBy('nombre')->get();
            $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $alumnos = collect();
        }

        return view('inventarios.edit', compact('inventario', 'sedes', 'alumnos', 'tipos', 'propietarios', 'estados', 'origenes'));
    }

    public function update(Request $request, InventarioItem $inventario)
    {
        $validated = $this->validateItem($request, $inventario->id);
        $sedeCambio = isset($validated['sede_id']) && (int) $validated['sede_id'] !== (int) $inventario->sede_id;
        $inventario->update($validated);
        $inventario->asegurarCodigo();
        if ($sedeCambio) {
            $this->registrar($inventario, 'sede', 'Cambio de sede');
        }

        return redirect()->route('inventarios.show', $inventario)->with('success', 'Item actualizado.');
    }

    public function destroy(InventarioItem $inventario)
    {
        $inventario->delete();

        return redirect()->route('inventarios.index')->with('success', 'Item eliminado.');
    }

    public function registrarMovimiento(Request $request, InventarioItem $inventario)
    {
        $data = $request->validate([
            'tipo' => 'required|in:'.implode(',', array_keys(InventarioMovimiento::TIPOS)),
            'nota' => 'nullable|string|max:400',
            'sede_id' => 'nullable|exists:sedes,id',
            'estado' => 'nullable|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
        ]);
        if (! empty($data['sede_id']) && (int) $data['sede_id'] !== (int) $inventario->sede_id) {
            $inventario->update(['sede_id' => $data['sede_id']]);
            if (($data['tipo'] ?? '') !== 'sede') {
                // La ubicación cambió aunque el tipo sea otro (p. ej. reparación en otra sede).
            }
        } elseif (($data['tipo'] ?? '') === 'sede' && ! empty($data['sede_id'])) {
            $inventario->update(['sede_id' => $data['sede_id']]);
        }
        if (! empty($data['estado']) && $data['estado'] !== $inventario->estado) {
            $inventario->update(['estado' => $data['estado']]);
        }
        $this->registrar($inventario, $data['tipo'], $data['nota'] ?? null, $data['sede_id'] ?? $inventario->sede_id);

        return back()->with('success', 'Movimiento registrado.'.(! empty($data['estado']) ? ' Estado: '.(InventarioItem::ESTADOS[$data['estado']] ?? $data['estado']).'.' : ''));
    }

    private function registrar(InventarioItem $item, string $tipo, ?string $nota, ?int $sedeId = null): void
    {
        if (! Schema::hasTable('inventario_movimientos')) {
            return;
        }
        InventarioMovimiento::query()->create([
            'inventario_item_id' => $item->id,
            'user_id' => auth()->id(),
            'sede_id' => $sedeId ?? $item->sede_id,
            'tipo' => $tipo,
            'nota' => $nota,
        ]);
    }

    private function validateItem(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'tipo' => 'required|string|max:30',
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:40|unique:inventario_items,codigo'.($id ? ','.$id : ''),
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
        $codigo = trim((string) ($validated['codigo'] ?? ''));
        $validated['codigo'] = $codigo === '' ? null : $codigo;

        if (! $validated['es_consumible']) {
            $validated['cantidad'] = 1;
            $validated['unidad'] = $validated['unidad'] ?: 'u';
        }

        return $validated;
    }
}
