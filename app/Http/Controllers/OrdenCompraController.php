<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $ordenes = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        $sedes = collect();

        if (Schema::hasTable('ordenes_compra')) {
            try {
                $query = OrdenCompra::with(['sede', 'creador'])->orderByDesc('created_at');
                $request->user()->acceso()->alcance('compras.view')->aplicarPorSede($query);

                if ($request->filled('sede_id')) {
                    $query->where('sede_id', $request->sede_id);
                }

                if ($request->filled('estado')) {
                    $query->where('estado', $request->estado);
                }

                $ordenes = $query->paginate(20);
                $sedes = Sede::orderBy('nombre')->get();
            } catch (QueryException $e) {
                // mantener valores por defecto vacíos
            }
        } else {
            if (Schema::hasTable('sedes')) {
                try {
                    $sedes = Sede::orderBy('nombre')->get();
                } catch (QueryException $e) {
                    // mantener collect()
                }
            }
        }

        return view('ordenes-compra.index', [
            'ordenes' => $ordenes,
            'sedes' => $sedes,
            'estados' => OrdenCompra::ESTADOS,
        ]);
    }

    public function create(Request $request)
    {
        $sedes = collect();
        if (Schema::hasTable('sedes')) {
            try {
                $sedes = Sede::orderBy('nombre')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('ordenes-compra.create', [
            'sedes' => $sedes,
            'motivos' => OrdenCompra::MOTIVOS,
            'estados' => OrdenCompra::ESTADOS,
            'defaults' => [
                'sede_id' => $request->get('sede_id'),
                'motivo' => $request->get('motivo', 'reposicion'),
                'estado' => 'borrador',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validatedOrden = $this->validateOrden($request);
        $itemsData = $this->validateItems($request);
        $this->asegurarAlcance('compras.create', (int) $validatedOrden['sede_id']);
        $this->asegurarAprobacion(null, $validatedOrden);

        DB::transaction(function () use ($validatedOrden, $itemsData, &$orden) {
            $orden = OrdenCompra::create($validatedOrden);

            $total = 0;
            foreach ($itemsData as $data) {
                $item = new OrdenCompraItem($data);
                $item->orden_compra_id = $orden->id;
                $item->subtotal_estimado = $item->cantidad * ($item->precio_estimado ?? 0);
                $item->save();
                $total += $item->subtotal_estimado;
            }

            $orden->update(['total_estimado' => $total]);
        });

        return redirect()->route('ordenes-compra.show', $orden)->with('success', 'Orden de compra creada.');
    }

    public function show(OrdenCompra $ordenes_compra)
    {
        $this->asegurarAlcance('compras.view', (int) $ordenes_compra->sede_id);
        $ordenes_compra->load(['sede', 'creador', 'items']);

        return view('ordenes-compra.show', ['orden' => $ordenes_compra]);
    }

    public function edit(OrdenCompra $ordenes_compra)
    {
        $this->asegurarAlcance('compras.create', (int) $ordenes_compra->sede_id);
        $sedes = collect();
        if (Schema::hasTable('ordenes_compra') && Schema::hasTable('sedes')) {
            try {
                $ordenes_compra->load('items');
                $sedes = Sede::orderBy('nombre')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('ordenes-compra.edit', [
            'orden' => $ordenes_compra,
            'sedes' => $sedes,
            'motivos' => OrdenCompra::MOTIVOS,
            'estados' => OrdenCompra::ESTADOS,
        ]);
    }

    public function update(Request $request, OrdenCompra $ordenes_compra)
    {
        $this->asegurarAlcance('compras.create', (int) $ordenes_compra->sede_id);
        $validatedOrden = $this->validateOrden($request);
        $itemsData = $this->validateItems($request);
        $this->asegurarAlcance('compras.create', (int) $validatedOrden['sede_id']);
        $this->asegurarAprobacion($ordenes_compra, $validatedOrden);

        DB::transaction(function () use ($ordenes_compra, $validatedOrden, $itemsData) {
            $ordenes_compra->update($validatedOrden);

            $ordenes_compra->items()->delete();
            $total = 0;
            foreach ($itemsData as $data) {
                $item = new OrdenCompraItem($data);
                $item->orden_compra_id = $ordenes_compra->id;
                $item->subtotal_estimado = $item->cantidad * ($item->precio_estimado ?? 0);
                $item->save();
                $total += $item->subtotal_estimado;
            }

            $ordenes_compra->update(['total_estimado' => $total]);
        });

        return redirect()->route('ordenes-compra.show', $ordenes_compra)->with('success', 'Orden de compra actualizada.');
    }

    public function destroy(OrdenCompra $ordenes_compra)
    {
        $this->asegurarAlcance('compras.create', (int) $ordenes_compra->sede_id);
        $ordenes_compra->delete();

        return redirect()->route('ordenes-compra.index')->with('success', 'Orden de compra eliminada.');
    }

    private function asegurarAlcance(string $permiso, int $sedeId): void
    {
        if (! auth()->user()->acceso()->puedeEnSede($permiso, $sedeId)) {
            abort(403, 'No podés operar compras de esa sede.');
        }
    }

    /**
     * Pasar una orden a aprobada/recibida es una decisión de gasto: requiere compras.approve.
     *
     * @param  array<string, mixed>  $datos
     */
    private function asegurarAprobacion(?OrdenCompra $orden, array $datos): void
    {
        $nuevo = $datos['estado'] ?? null;
        $cambia = $orden === null || $orden->estado !== $nuevo;
        if ($cambia && in_array($nuevo, ['aprobada', 'recibida'], true)
            && ! auth()->user()->acceso()->puedeEnSede('compras.approve', (int) $datos['sede_id'])) {
            abort(403, 'Aprobar una orden de compra requiere permiso de aprobación.');
        }
    }

    private function validateOrden(Request $request): array
    {
        $data = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'motivo' => 'required|in:'.implode(',', array_keys(OrdenCompra::MOTIVOS)),
            'estado' => 'required|in:'.implode(',', array_keys(OrdenCompra::ESTADOS)),
            'fecha_objetivo' => 'nullable|date',
            'justificacion' => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();

        return $data;
    }

    private function validateItems(Request $request): array
    {
        $tipos = $request->input('item_tipo', []);
        $familias = $request->input('item_familia', []);
        $descripciones = $request->input('item_descripcion', []);
        $marcas = $request->input('item_marca', []);
        $modelos = $request->input('item_modelo', []);
        $medidas = $request->input('item_medida', []);
        $cantidades = $request->input('item_cantidad', []);
        $unidades = $request->input('item_unidad', []);
        $precios = $request->input('item_precio', []);

        $items = [];

        foreach ($descripciones as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }

            $cantidad = isset($cantidades[$i]) ? (float) $cantidades[$i] : 1;
            if ($cantidad <= 0) {
                $cantidad = 1;
            }

            $precio = isset($precios[$i]) && $precios[$i] !== '' ? (float) $precios[$i] : null;

            $items[] = [
                'tipo' => $tipos[$i] ?? null,
                'familia' => $familias[$i] ?? null,
                'descripcion' => $desc,
                'marca' => $marcas[$i] ?? null,
                'modelo' => $modelos[$i] ?? null,
                'medida' => $medidas[$i] ?? null,
                'cantidad' => $cantidad,
                'unidad' => $unidades[$i] ?? 'u',
                'precio_estimado' => $precio,
            ];
        }

        if (empty($items)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'Agregá al menos un ítem a la orden de compra.',
            ]);
        }

        return $items;
    }
}
