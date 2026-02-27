<?php

namespace App\Http\Controllers;

use App\Models\FacturacionMensual;
use App\Models\Sede;
use Illuminate\Http\Request;

class FacturacionMensualController extends Controller
{
    public function index(Request $request)
    {
        $query = FacturacionMensual::with('sede');
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }
        if ($request->filled('año')) {
            $query->where('año', $request->año);
        }
        $facturacion = $query->orderBy('año', 'desc')->orderBy('mes', 'desc')->paginate(24);
        $sedes = Sede::where('activo', true)->get();
        return view('facturacion-mensual.index', compact('facturacion', 'sedes'));
    }

    public function create()
    {
        $sedes = Sede::where('activo', true)->get();
        $meses = FacturacionMensual::nombresMeses();
        return view('facturacion-mensual.create', compact('sedes', 'meses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sede_id' => 'nullable|exists:sedes,id',
            'año' => 'required|integer|min:2020|max:2030',
            'mes' => 'required|integer|min:1|max:12',
            'cantidad_alumnos' => 'required|integer|min:0',
            'monto_facturado' => 'required|numeric|min:0',
            'monto_previsto' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string|max:500',
        ]);

        $exists = FacturacionMensual::where('sede_id', $validated['sede_id'])
            ->where('año', $validated['año'])
            ->where('mes', $validated['mes'])
            ->first();
        if ($exists) {
            return back()->withErrors(['mes' => 'Ya existe facturación para esa sede, año y mes.'])->withInput();
        }

        FacturacionMensual::create($validated);
        return redirect()->route('facturacion-mensual.index')->with('success', 'Facturación mensual registrada.');
    }

    public function edit(FacturacionMensual $facturacionMensual)
    {
        $facturacionMensual->load('sede');
        $sedes = Sede::where('activo', true)->get();
        $meses = FacturacionMensual::nombresMeses();
        return view('facturacion-mensual.edit', compact('facturacionMensual', 'sedes', 'meses'));
    }

    public function update(Request $request, FacturacionMensual $facturacionMensual)
    {
        $validated = $request->validate([
            'cantidad_alumnos' => 'required|integer|min:0',
            'monto_facturado' => 'required|numeric|min:0',
            'monto_previsto' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string|max:500',
        ]);
        $facturacionMensual->update($validated);
        return redirect()->route('facturacion-mensual.index')->with('success', 'Facturación actualizada.');
    }
}
