<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\ComprobanteService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ComprobanteCuotaAlumnoGestionController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            abort(503, 'Ejecutá migraciones para habilitar esta sección.');
        }

        /** @var User $user */
        $user = auth()->user();
        $query = app(ComprobanteService::class)->consulta($user)
            ->with(['alumno', 'sede', 'items.bloque.sede', 'items.cuota', 'pago'])
            ->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        $comprobantes = $query->paginate(20)->withQueryString();

        return view('comprobante_cuota_gestion.index', compact('comprobantes'));
    }

    public function create()
    {
        $alumnos = $this->alumnosVisiblesParaCarga();
        $user = auth()->user();
        $bloquesQ = Bloque::query()->where('activo', true)->with('sede')->orderBy('nombre');
        $user->acceso()->alcance('comprobantes.create')->aplicarBloques($bloquesQ);
        $bloques = $bloquesQ->get();

        return view('comprobante_cuota_gestion.create', compact('alumnos', 'bloques'));
    }

    public function store(Request $request, ComprobanteService $comprobantes)
    {
        $validated = $request->validate([
            'alumno_id' => 'required|exists:alumnos,id',
            'año' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'fecha_pago' => 'required|date',
            'bloque_ids' => 'required|array|min:1',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ]);

        $alumno = Alumno::query()->with(['bloques', 'bloque', 'sede'])->findOrFail($validated['alumno_id']);
        $comprobantes->cargarPorGestion(
            $request->user(),
            $alumno,
            (int) $validated['año'],
            (int) $validated['mes'],
            $validated['fecha_pago'],
            array_map('intval', $validated['bloque_ids']),
            $request->file('comprobante'),
            $validated['notas'] ?? null,
        );

        return redirect()->route('comprobantes-cuota-alumnos.index')
            ->with('success', 'Comprobante cargado. Queda pendiente de revisión.');
    }

    public function show(int $id)
    {
        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        $this->authorizeVer($comprobanteCuotaAlumno);
        $comprobanteCuotaAlumno->load(['alumno.sede', 'sede', 'items.bloque.sede', 'items.cuota', 'pago']);

        return view('comprobante_cuota_gestion.show', compact('comprobanteCuotaAlumno'));
    }

    public function comprobante(int $id)
    {
        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        $this->authorizeVer($comprobanteCuotaAlumno);
        if (! $comprobanteCuotaAlumno->comprobante_path) {
            abort(404);
        }
        $disk = Storage::disk('comprobantes');
        $ext = strtolower((string) pathinfo($comprobanteCuotaAlumno->comprobante_path, PATHINFO_EXTENSION));
        if ($ext === '' || ! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }
        $name = 'comprobante-alumno-'.$comprobanteCuotaAlumno->id.'.'.$ext;

        return $disk->response($comprobanteCuotaAlumno->comprobante_path, $name);
    }

    public function marcarVisto(Request $request, int $id)
    {
        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        $this->authorizeVer($comprobanteCuotaAlumno);

        if ($comprobanteCuotaAlumno->estaPagado()) {
            return back()->with('success', 'Este comprobante ya está pagado.');
        }

        app(ComprobanteService::class)->marcarVisto($comprobanteCuotaAlumno);

        return back()->with('success', 'Marcado como visto (sin registrar pago).');
    }

    public function aprobarYRegistrarPago(Request $request, int $id, ComprobanteService $comprobantes)
    {
        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        // Aprobar genera un pago: requiere comprobantes.approve sobre el alumno del comprobante.
        if (! $comprobantes->puedeAprobar($request->user(), $comprobanteCuotaAlumno)) {
            abort(403, 'No podés registrar el pago de este comprobante.');
        }

        try {
            $result = $comprobantes->aprobar($comprobanteCuotaAlumno, $request->user(), $request->boolean('liquidar_profesor', true));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pagos.show', $result['pago'])
            ->with('success', $result['mensaje']);
    }

    private function authorizeVer(ComprobanteCuotaAlumno $c): void
    {
        if (! app(ComprobanteService::class)->puedeVer(auth()->user(), $c)) {
            abort(403);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Alumno>
     */
    private function alumnosVisiblesParaCarga()
    {
        $q = Alumno::query()->where('activo', true)->orderBy('nombre_apellido');
        $alcance = auth()->user()->acceso()->alcance('comprobantes.create');
        if ($alcance->estaVacio()) {
            return collect();
        }
        $alcance->aplicarAlumnos($q);

        return $q->limit(1000)->get(['id', 'nombre_apellido', 'sede_id', 'bloque_id']);
    }
}
