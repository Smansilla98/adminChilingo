<?php

namespace App\Http\Controllers;

use App\Domain\Inventario\InventarioService;
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
            if ($hit && $request->user()->can('view', $hit)) {
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
            $alcance = $request->user()->acceso()->alcance('inventario.view');
            $alcance->aplicarPorSede($query);

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
            $sedes = $alcance->aplicarPorSede(Sede::orderBy('nombre'), 'id')->get();
        } catch (QueryException $e) {
            $items = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
            $sedes = collect();
        }

        return view('inventarios.index', compact('items', 'sedes', 'tipos', 'propietarios'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', InventarioItem::class);
        $tipos = InventarioItem::TIPOS;
        $propietarios = InventarioItem::PROPIETARIOS;
        $estados = InventarioItem::ESTADOS;
        $origenes = InventarioItem::ORIGENES;
        $defaults = [
            'sede_id' => $request->get('sede_id'),
            'tipo' => $request->get('tipo', 'instrumento'),
        ];

        try {
            $sedes = $request->user()->acceso()->alcance('inventario.create')->aplicarPorSede(Sede::orderBy('nombre'), 'id')->get();
            $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $alumnos = collect();
        }

        return view('inventarios.create', compact('sedes', 'alumnos', 'tipos', 'propietarios', 'estados', 'origenes', 'defaults'));
    }

    public function store(Request $request, InventarioService $inventario)
    {
        $this->authorize('create', InventarioItem::class);
        $validated = $this->validateItem($request);
        $this->asegurarSede('inventario.create', (int) $validated['sede_id']);
        $item = $inventario->crear($validated, $request->user()->id);

        return redirect()->route('inventarios.show', $item)->with('success', 'Item de inventario creado. Código '.$item->codigo.'.');
    }

    public function show(InventarioItem $inventario)
    {
        $this->authorize('view', $inventario);
        $inventario->load(['sede', 'alumno']);
        if (Schema::hasTable('inventario_movimientos')) {
            $inventario->load(['movimientos.autor', 'movimientos.sede']);
        }
        $sedes = Sede::orderBy('nombre')->get();

        return view('inventarios.show', ['item' => $inventario, 'sedes' => $sedes]);
    }

    public function edit(InventarioItem $inventario)
    {
        $this->authorize('update', $inventario);
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
        $this->authorize('update', $inventario);
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
        $this->authorize('delete', $inventario);
        $inventario->delete();

        return redirect()->route('inventarios.index')->with('success', 'Item eliminado.');
    }

    public function registrarMovimiento(Request $request, InventarioItem $inventario, InventarioService $servicio)
    {
        $this->authorize('update', $inventario);
        $data = $request->validate([
            'tipo' => 'required|in:'.implode(',', array_keys(InventarioMovimiento::TIPOS)),
            'nota' => 'nullable|string|max:400',
            'sede_id' => 'nullable|exists:sedes,id',
            'estado' => 'nullable|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
        ]);
        $servicio->registrarMovimiento(
            $inventario,
            $request->user()->id,
            $data['tipo'],
            $data['nota'] ?? null,
            ! empty($data['sede_id']) ? (int) $data['sede_id'] : null,
            $data['estado'] ?? null,
        );

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
        $validated = $request->validate(InventarioService::reglas($id));
        $validated['es_consumible'] = $request->boolean('es_consumible');
        $validated['utilitario'] = $request->boolean('utilitario');

        return InventarioService::normalizar($validated);
    }

    private function asegurarSede(string $permiso, int $sedeId): void
    {
        if (! auth()->user()->acceso()->puedeEnSede($permiso, $sedeId)) {
            abort(403, 'No podés cargar inventario en esa sede.');
        }
    }
}
