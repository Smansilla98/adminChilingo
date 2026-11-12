<?php

namespace App\Http\Controllers;

use App\Domain\Reportes\TableroDireccionService;
use App\Exports\TableroDireccionExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** Tablero de dirección (web): evolución de 12 meses por sede o global, con Excel e impresión. */
class TableroDireccionController extends Controller
{
    public function __construct(private TableroDireccionService $tablero) {}

    public function index(Request $request)
    {
        $sedeId = $request->integer('sede_id') ?: null;

        return view('direccion.index', [
            'd' => $this->tablero->datos($request->user(), $sedeId),
            'sedes' => $this->tablero->sedesDisponibles($request->user()),
            'sedeId' => $sedeId,
        ]);
    }

    public function excel(Request $request)
    {
        $d = $this->tablero->datos($request->user(), $request->integer('sede_id') ?: null);

        return Excel::download(new TableroDireccionExport($this->tablero->hojasExcel($d)), 'tablero-direccion-'.now()->format('Y-m').'.xlsx');
    }

    public function imprimible(Request $request)
    {
        $sedeId = $request->integer('sede_id') ?: null;

        return view('direccion.imprimible', [
            'd' => $this->tablero->datos($request->user(), $sedeId),
            'sede' => $sedeId ? $this->tablero->sedesDisponibles($request->user())->firstWhere('id', $sedeId)?->nombre : null,
            'imprimir' => true,
        ]);
    }
}
