<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Cuota;
use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $query = Pago::with(['detalles.alumno', 'detalles.cuota', 'registradoPor']);
        if ($request->filled('alumno_id')) {
            $query->whereHas('detalles', fn($q) => $q->where('alumno_id', $request->alumno_id));
        }
        if ($request->filled('cuota_id')) {
            $query->whereHas('detalles', fn($q) => $q->where('cuota_id', $request->cuota_id));
        }
        if ($request->filled('desde')) {
            $query->where('fecha_pago', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->where('fecha_pago', '<=', $request->hasta);
        }
        $pagos = $query->orderBy('fecha_pago', 'desc')->paginate(20);
        $alumnos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get();
        $cuotas = Cuota::where('activo', true)->orderBy('año', 'desc')->orderBy('mes')->get();
        return view('pagos.index', compact('pagos', 'alumnos', 'cuotas'));
    }

    public function create()
    {
        $alumnos = Alumno::where('activo', true)->with('sede')->orderBy('nombre_apellido')->get();
        $cuotas = Cuota::where('activo', true)->orderBy('año', 'desc')->orderBy('mes')->get();
        return view('pagos.create', compact('alumnos', 'cuotas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha_pago' => 'required|date',
            'cuota_id' => 'required|exists:cuotas,id',
            'alumno_ids' => 'required|array|min:1',
            'alumno_ids.*' => 'exists:alumnos,id',
            'monto_total' => 'required|numeric|min:0',
            'comprobante' => 'nullable|file|mimes:pdf|max:10240',
            'notas' => 'nullable|string|max:1000',
        ]);

        $alumnoIds = array_values(array_filter($validated['alumno_ids']));
        if (empty($alumnoIds)) {
            return back()->withErrors(['alumno_ids' => 'Seleccione al menos un alumno.'])->withInput();
        }

        $path = null;
        if ($request->hasFile('comprobante')) {
            $path = $request->file('comprobante')->store('pagos', 'public');
        }

        $pago = Pago::create([
            'fecha_pago' => $validated['fecha_pago'],
            'monto_total' => $validated['monto_total'],
            'comprobante_path' => $path,
            'notas' => $validated['notas'] ?? null,
            'registrado_por' => auth()->id(),
        ]);

        $montoPorAlumno = round((float) $validated['monto_total'] / count($alumnoIds), 2);
        foreach ($alumnoIds as $alumnoId) {
            PagoDetalle::create([
                'pago_id' => $pago->id,
                'alumno_id' => $alumnoId,
                'cuota_id' => $validated['cuota_id'],
                'monto' => $montoPorAlumno,
            ]);
        }

        return redirect()->route('pagos.index')->with('success', 'Pago registrado correctamente.');
    }

    public function show(Pago $pago)
    {
        $pago->load(['detalles.alumno', 'detalles.cuota', 'registradoPor']);
        return view('pagos.show', compact('pago'));
    }

    public function downloadComprobante(Pago $pago)
    {
        if (!$pago->comprobante_path) {
            abort(404);
        }
        return Storage::disk('public')->download(
            $pago->comprobante_path,
            'comprobante-pago-' . $pago->id . '.pdf'
        );
    }
}
