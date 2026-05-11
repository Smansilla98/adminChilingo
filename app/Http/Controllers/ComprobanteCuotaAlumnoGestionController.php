<?php

namespace App\Http\Controllers;

use App\Models\ComprobanteCuotaAlumno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
            ->with(['alumno', 'sede', 'items.bloque', 'items.cuota'])
            ->orderByDesc('created_at');

        if ($user->isProfesor() && ! $user->isAdmin()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $query->whereHas('items', fn ($q) => $q->whereIn('bloque_id', $ids !== [] ? $ids : [0]));
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
        $comprobanteCuotaAlumno->load(['alumno.sede', 'sede', 'items.bloque', 'items.cuota']);

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
        $name = 'comprobante-alumno-' . $comprobanteCuotaAlumno->id . '.' . $ext;

        return $disk->response($comprobanteCuotaAlumno->comprobante_path, $name);
    }

    public function marcarVisto(Request $request, int $id)
    {
        $comprobanteCuotaAlumno = ComprobanteCuotaAlumno::query()->findOrFail($id);
        $this->authorizeVer($comprobanteCuotaAlumno);
        $comprobanteCuotaAlumno->update(['estado' => 'visto']);

        return back()->with('success', 'Marcado como visto.');
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

