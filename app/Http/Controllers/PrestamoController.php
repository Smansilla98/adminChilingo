<?php

namespace App\Http\Controllers;

use App\Domain\Inventario\PrestamoService;
use App\Models\InventarioItem;
use App\Models\InventarioPrestamo;
use Illuminate\Http\Request;

/** Préstamos de instrumentos desde la ficha del ítem (panel web). */
class PrestamoController extends Controller
{
    public function store(Request $request, InventarioItem $inventario, PrestamoService $prestamos)
    {
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.update', $inventario->sede_id ? (int) $inventario->sede_id : null), 403);
        $datos = $request->validate(PrestamoService::reglas());
        $prestamos->prestar($inventario, $datos, $request->user());

        return back()->with('success', 'Préstamo registrado.');
    }

    public function devolver(Request $request, InventarioPrestamo $prestamo, PrestamoService $prestamos)
    {
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.update', $prestamo->item->sede_id ? (int) $prestamo->item->sede_id : null), 403);
        $datos = $request->validate(PrestamoService::reglasDevolucion());
        $prestamos->devolver($prestamo, $datos, $request->user());

        return back()->with('success', 'Devolución registrada.');
    }
}
