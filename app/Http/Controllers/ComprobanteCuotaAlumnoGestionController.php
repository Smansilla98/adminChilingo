<?php

namespace App\Http\Controllers;

use App\Models\ComprobanteCuotaAlumno;
use App\Models\User;
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

        if ($user->isProfesor() && ! $user->isAdmin() && ! $user->isCoordinadorSede()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $query->whereHas('items', fn ($q) => $q->whereIn('bloque_id', $ids !== [] ? $ids : [0]));
        } elseif ($user->isCoordinadorSede() && ! $user->isAdmin()) {
            $sedeIds = $user->sedeIdsCoordinadas();
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
        $c->loadMissing('items');
        /** @var User $user */
        $user = auth()->user();
        if ($user->isAdmin()) {
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
}
