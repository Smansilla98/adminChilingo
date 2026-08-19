<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\User;
use App\Services\ComprobanteCuotaRegistroService;
use App\Services\PagoDesdeComprobanteService;
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

        $query = ComprobanteCuotaAlumno::query()
            ->with(['alumno', 'sede', 'items.bloque.sede', 'items.cuota', 'pago'])
            ->orderByDesc('created_at');

        if ($user->isProfesor() && ! $user->isAdmin() && ! $user->acotaPorSede()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $query->whereHas('items', fn ($q) => $q->whereIn('bloque_id', $ids !== [] ? $ids : [0]));
        } elseif ($user->acotaPorSede()) {
            $sedeIds = $user->sedeIdsOperativas();
            $query->where(function ($q) use ($sedeIds) {
                $ids = $sedeIds !== [] ? $sedeIds : [0];
                $q->whereIn('sede_id', $ids)
                    ->orWhereHas('items.bloque', fn ($b) => $b->whereIn('sede_id', $ids));
            });
        }

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
        if ($user && ! $user->isAdmin()) {
            $ids = $user->bloqueIdsPermitidos() ?: [0];
            $bloquesQ->whereIn('id', $ids);
        }
        $bloques = $bloquesQ->get();

        return view('comprobante_cuota_gestion.create', compact('alumnos', 'bloques'));
    }

    public function store(Request $request, ComprobanteCuotaRegistroService $registro)
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
        $user = auth()->user();
        $alumno->loadMissing(['bloques', 'bloque']);
        if (! $user?->puedeGestionarAlumno($alumno) && ! $user?->isAdmin()) {
            abort(403);
        }

        $sedeId = (int) ($alumno->sede_id ?: $alumno->bloque?->sede_id ?: $alumno->bloques->first()?->sede_id);
        if ($sedeId <= 0) {
            return back()->withErrors(['alumno_id' => 'El alumno no tiene sede.'])->withInput();
        }

        foreach (array_map('intval', $validated['bloque_ids']) as $bid) {
            if ($user && ! $user->isAdmin() && ! $user->puedeAccederBloque($bid)) {
                abort(403);
            }
        }

        $registro->registrar(
            $alumno,
            $sedeId,
            (int) $validated['año'],
            (int) $validated['mes'],
            $validated['fecha_pago'],
            $validated['bloque_ids'],
            $request->file('comprobante'),
            $validated['notas'] ?? 'Cargado por docente/administración.',
            (int) $user->id,
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

        $comprobanteCuotaAlumno->update(['estado' => 'visto']);

        return back()->with('success', 'Marcado como visto (sin registrar pago).');
    }

    public function aprobarYRegistrarPago(Request $request, int $id, PagoDesdeComprobanteService $service)
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Solo administración puede registrar el pago desde el comprobante.');
        }

        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        $this->authorizeVer($comprobanteCuotaAlumno);

        try {
            $result = $service->aprobar(
                $comprobanteCuotaAlumno,
                (int) auth()->id(),
                $request->boolean('liquidar_profesor', true)
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pagos.show', $result['pago'])
            ->with('success', $result['mensaje']);
    }

    private function authorizeVer(ComprobanteCuotaAlumno $c): void
    {
        $c->loadMissing(['items.bloque']);
        /** @var User $user */
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        if ($user->acotaPorSede()) {
            $sedes = $user->sedeIdsOperativas();
            $sid = (int) $c->sede_id;
            $okSede = in_array($sid, $sedes, true)
                || $c->items->contains(fn ($i) => $i->bloque && in_array((int) $i->bloque->sede_id, $sedes, true));
            if (! $okSede) {
                abort(403);
            }

            return;
        }
        if (! $user->isProfesor()) {
            abort(403);
        }
        $prof = $user->profesor;
        $ids = collect($prof ? $prof->bloqueIdsDondeParticipa()->all() : []);
        $ok = $c->items->contains(fn ($i) => $ids->contains((int) $i->bloque_id));
        if (! $ok) {
            abort(403);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Alumno>
     */
    private function alumnosVisiblesParaCarga()
    {
        $user = auth()->user();
        $q = Alumno::query()->where('activo', true)->orderBy('nombre_apellido');
        if ($user && $user->isAdmin()) {
            return $q->limit(500)->get(['id', 'nombre_apellido', 'sede_id', 'bloque_id']);
        }
        if ($user && $user->acotaPorSede()) {
            $ids = $user->sedeIdsOperativas() ?: [0];
            $q->where(function ($inner) use ($ids) {
                $inner->whereIn('sede_id', $ids)
                    ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.sede_id', $ids));
            });

            return $q->get(['id', 'nombre_apellido', 'sede_id', 'bloque_id']);
        }
        if ($user && $user->isProfesor()) {
            $ids = $user->bloqueIdsPermitidos() ?: [0];
            $q->where(function ($inner) use ($ids) {
                $inner->whereIn('bloque_id', $ids)
                    ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $ids));
            });

            return $q->get(['id', 'nombre_apellido', 'sede_id', 'bloque_id']);
        }

        return collect();
    }
}
