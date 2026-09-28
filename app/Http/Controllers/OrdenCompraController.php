<?php

namespace App\Http\Controllers;

use App\Domain\Compras\CompraService;
use App\Models\OrdenCompra;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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

    public function store(Request $request, CompraService $compras)
    {
        $orden = $compras->guardar(null, $request->validate($compras->reglas()), $this->itemsDelFormulario($request), $request->user());

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

    public function update(Request $request, OrdenCompra $ordenes_compra, CompraService $compras)
    {
        $compras->asegurarAlcance($request->user(), 'compras.create', (int) $ordenes_compra->sede_id);
        $compras->guardar($ordenes_compra, $request->validate($compras->reglas()), $this->itemsDelFormulario($request), $request->user());

        return redirect()->route('ordenes-compra.show', $ordenes_compra)->with('success', 'Orden de compra actualizada.');
    }

    public function destroy(OrdenCompra $ordenes_compra, CompraService $compras)
    {
        $compras->eliminar($ordenes_compra, auth()->user());

        return redirect()->route('ordenes-compra.index')->with('success', 'Orden de compra eliminada.');
    }

    private function asegurarAlcance(string $permiso, int $sedeId): void
    {
        app(CompraService::class)->asegurarAlcance(auth()->user(), $permiso, $sedeId);
    }

    /**
     * El formulario web manda los ítems en columnas paralelas (item_descripcion[], item_cantidad[]…).
     *
     * @return list<array<string, mixed>>
     */
    private function itemsDelFormulario(Request $request): array
    {
        $items = [];
        foreach ($request->input('item_descripcion', []) as $i => $desc) {
            $items[] = [
                'descripcion' => $desc,
                'tipo' => $request->input("item_tipo.$i"),
                'familia' => $request->input("item_familia.$i"),
                'marca' => $request->input("item_marca.$i"),
                'modelo' => $request->input("item_modelo.$i"),
                'medida' => $request->input("item_medida.$i"),
                'cantidad' => $request->input("item_cantidad.$i"),
                'unidad' => $request->input("item_unidad.$i"),
                'precio_estimado' => $request->input("item_precio.$i"),
            ];
        }

        return $items;
    }
}
