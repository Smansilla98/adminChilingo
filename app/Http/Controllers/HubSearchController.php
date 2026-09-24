<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ProgramaRitmo;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HubSearchController extends Controller
{
    public function __invoke(Request $request, AmbitoSedeService $ambito)
    {
        /** @var User $user */
        $user = auth()->user();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$q.'%';
        $results = [];
        $esAdmin = $user->isAdmin();
        $acceso = $user->acceso();

        // Alumnos
        if (Schema::hasTable('alumnos') && ($esAdmin || $user->tieneAccesoModulo('profesor.mis_alumnos') || $user->tieneAccesoModulo('admin.alumnos'))) {
            $aq = Alumno::query()
                ->with(['sede', 'bloques.sede', 'bloque.sede'])
                ->where(function ($w) use ($like) {
                    $w->where('nombre_apellido', 'like', $like)
                        ->orWhere('dni', 'like', $like)
                        ->orWhere('telefono', 'like', $like);
                })
                ->where('activo', true)
                ->limit(8);

            $user->acceso()->alcance('alumnos.view')->aplicarAlumnos($aq);

            foreach ($aq->get() as $alumno) {
                $bloques = $alumno->bloques->isNotEmpty()
                    ? $alumno->bloques
                    : collect([$alumno->bloque])->filter();
                $meta = $bloques->map(function ($b) {
                    $sede = $b?->sede?->nombre;

                    return trim(($b?->nombre ?? '').($sede ? ' · '.$sede : ''));
                })->filter()->take(2)->implode(', ');

                $href = ($esAdmin || $user->puedeGestionarOperativo())
                    ? route('alumnos.show', $alumno)
                    : route('profesor.alumnos.show', $alumno);

                $results[] = [
                    'label' => $alumno->nombre_apellido,
                    'meta' => $meta ?: ($alumno->sede?->nombre ?? 'Alumno'),
                    'href' => $href,
                    'icon' => 'bi-person',
                    'group' => 'Alumnos',
                ];
            }
        }

        // Bloques
        if (Schema::hasTable('bloques') && ($esAdmin || $user->tieneAccesoModulo('profesor.mis_bloques') || $user->tieneAccesoModulo('admin.bloques'))) {
            $bq = Bloque::query()->with('sede')->where('nombre', 'like', $like)->where('activo', true)->limit(6);
            $acceso->alcance('bloques.view')->aplicarBloques($bq);
            foreach ($bq->get() as $bloque) {
                $hrefMatrix = ($esAdmin || $user->puedeGestionarOperativo())
                    ? route('asistencias.index', ['bloque_id' => $bloque->id, 'mes' => now()->month, 'año' => now()->year])
                    : route('profesor.asistencias.matrix', ['bloque_id' => $bloque->id, 'mes' => now()->month, 'año' => now()->year]);

                $results[] = [
                    'label' => $bloque->nombre,
                    'meta' => ($bloque->sede?->nombre ?? 'Bloque').' · Abrir matriz',
                    'href' => $hrefMatrix,
                    'icon' => 'bi-collection',
                    'group' => 'Bloques',
                ];
            }
        }

        // Comprobantes pendientes
        if (Schema::hasTable('comprobantes_cuota_alumnos') && $user->tieneAccesoModulo('comprobantes')) {
            $cq = ComprobanteCuotaAlumno::query()
                ->with(['alumno', 'sede'])
                ->where('estado', 'pendiente')
                ->where(function ($w) use ($like) {
                    $w->where('id', 'like', $like)
                        ->orWhereHas('alumno', fn ($a) => $a->where('nombre_apellido', 'like', $like));
                })
                ->latest()
                ->limit(5);

            $alcance = $acceso->alcance('comprobantes.view');
            if (! $alcance->esGlobal()) {
                $cq->where(fn ($w) => $w->whereIn('sede_id', $alcance->sedeIds() ?: [0])
                    ->orWhereHas('items', fn ($i) => $i->whereIn('bloque_id', $alcance->bloqueIds() ?: [0]))
                    ->orWhereHas('items.bloque', fn ($b) => $b->whereIn('sede_id', $alcance->sedeIds() ?: [0])));
            }

            foreach ($cq->get() as $c) {
                $results[] = [
                    'label' => 'Comprobante #'.$c->id.' · '.($c->alumno?->nombre_apellido ?? 'Alumno'),
                    'meta' => 'Pendiente · $'.number_format((float) $c->monto_total, 2, ',', '.'),
                    'href' => route('comprobantes-cuota-alumnos.show', $c->id),
                    'icon' => 'bi-receipt',
                    'group' => 'Comprobantes',
                ];
            }
        }

        // Toques
        if (Schema::hasTable('programa_ritmos') && $user->tieneAccesoModulo('programa')) {
            $tq = ProgramaRitmo::query()->where('nombre', 'like', $like)->limit(5);
            if (Schema::hasColumn('programa_ritmos', 'publicado')) {
                $tq->where('publicado', true);
            }
            if (Schema::hasColumn('programa_ritmos', 'slug')) {
                foreach ($tq->get() as $t) {
                    try {
                        $results[] = [
                            'label' => $t->nombre,
                            'meta' => 'Toque · Programa',
                            'href' => route('programa.toque.show', $t),
                            'icon' => 'bi-music-note-list',
                            'group' => 'Programa',
                        ];
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        return response()->json(['results' => array_slice($results, 0, 20)]);
    }
}
