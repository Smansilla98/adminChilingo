<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\FacturacionService;
use App\Models\FacturacionMensual;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FacturacionMensualController extends Controller
{
    public function index(Request $request)
    {
        $facturacion = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 24);
        $sedes = collect();

        if (Schema::hasTable('facturacion_mensual')) {
            try {
                $query = FacturacionMensual::with('sede');
                $request->user()->acceso()->alcance('facturacion.view')->aplicarPorSede($query, 'sede_id', false, $request->user()->acceso()->puedeGlobal('facturacion.view'));
                if ($request->filled('sede_id')) {
                    $query->where('sede_id', $request->sede_id);
                }
                if ($request->filled('año')) {
                    $query->where('año', $request->año);
                }
                $facturacion = $query->orderBy('año', 'desc')->orderBy('mes', 'desc')->paginate(24);
            } catch (QueryException $e) {
                // mantener paginador vacío
            }
        }
        if (Schema::hasTable('sedes')) {
            try {
                $sedes = Sede::where('activo', true)->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('facturacion-mensual.index', compact('facturacion', 'sedes'));
    }

    public function create()
    {
        $sedes = collect();
        if (Schema::hasTable('sedes')) {
            try {
                $sedes = Sede::where('activo', true)->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        $meses = FacturacionMensual::nombresMeses();

        return view('facturacion-mensual.create', compact('sedes', 'meses'));
    }

    public function store(Request $request, FacturacionService $facturacion)
    {
        $facturacion->registrar($request->validate($facturacion->reglas()), $request->user());

        return redirect()->route('facturacion-mensual.index')->with('success', 'Facturación mensual registrada.');
    }

    public function edit(FacturacionMensual $facturacionMensual, FacturacionService $facturacion)
    {
        abort_unless($facturacion->puedeGestionar(auth()->user(), $facturacionMensual->sede_id), 403, 'No podés editar la facturación de esa sede.');
        $sedes = collect();
        if (Schema::hasTable('facturacion_mensual') && Schema::hasTable('sedes')) {
            try {
                $facturacionMensual->load('sede');
                $sedes = Sede::where('activo', true)->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        $meses = FacturacionMensual::nombresMeses();

        return view('facturacion-mensual.edit', compact('facturacionMensual', 'sedes', 'meses'));
    }

    public function update(Request $request, FacturacionMensual $facturacionMensual, FacturacionService $facturacion)
    {
        $facturacion->actualizar($facturacionMensual, $request->validate($facturacion->reglas(true)), $request->user());

        return redirect()->route('facturacion-mensual.index')->with('success', 'Facturación actualizada.');
    }
}
