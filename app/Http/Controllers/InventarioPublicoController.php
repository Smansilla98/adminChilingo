<?php

namespace App\Http\Controllers;

use App\Models\InventarioItem;
use Illuminate\Support\Facades\Schema;

class InventarioPublicoController extends Controller
{
    public function show(string $codigo)
    {
        abort_unless(Schema::hasTable('inventario_items'), 404);

        $item = InventarioItem::query()
            ->with('sede')
            ->where('codigo', $codigo)
            ->firstOrFail();

        return view('inventarios.publico', [
            'ficha' => $item->fichaPublica(),
        ]);
    }
}
