<?php

namespace App\Http\Controllers;

use App\Domain\Programa\GestionProgramaService;
use App\Models\ProgramaRitmo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Administración del programa: año de cada toque, si se sigue tocando, si forma
 * parte del programa y su nombre (con historial de nombres anteriores).
 */
class ProgramaGestionController extends Controller
{
    public const FILTROS = [
        'todos' => 'Todos',
        'en_programa' => 'En el programa',
        'no_vigentes' => 'Ya no se tocan',
        'retirados' => 'Fuera del programa',
    ];

    public function index(Request $request): View
    {
        $this->autorizar();
        abort_unless(Schema::hasColumn('programa_ritmos', 'en_programa'), 503, 'Falta migrar la base: php artisan migrate --force');

        $filtro = array_key_exists((string) $request->query('estado'), self::FILTROS) ? (string) $request->query('estado') : 'todos';
        $busqueda = trim((string) $request->query('q', ''));

        $q = ProgramaRitmo::query()
            ->when($filtro === 'en_programa', fn ($q) => $q->where('en_programa', true))
            ->when($filtro === 'no_vigentes', fn ($q) => $q->where('vigente', false))
            ->when($filtro === 'retirados', fn ($q) => $q->where('en_programa', false));

        $toques = ProgramaRitmo::traerOrdenados($q);
        if ($busqueda !== '') {
            $buscado = mb_strtolower($busqueda);
            $toques = $toques->filter(fn (ProgramaRitmo $t) => str_contains(mb_strtolower($t->nombre), $buscado)
                || collect($t->historialNombres())->contains(fn ($n) => str_contains(mb_strtolower($n['nombre']), $buscado)));
        }

        $totales = [
            'todos' => ProgramaRitmo::query()->count(),
            'en_programa' => ProgramaRitmo::query()->where('en_programa', true)->count(),
            'no_vigentes' => ProgramaRitmo::query()->where('vigente', false)->count(),
            'retirados' => ProgramaRitmo::query()->where('en_programa', false)->count(),
        ];

        return view('programa.gestion', [
            'porAño' => $toques->groupBy(fn (ProgramaRitmo $t) => (int) $t->año),
            'años' => ProgramaRitmo::años(),
            'filtros' => self::FILTROS,
            'filtro' => $filtro,
            'busqueda' => $busqueda,
            'totales' => $totales,
        ]);
    }

    public function update(Request $request, ProgramaRitmo $programaRitmo, GestionProgramaService $gestion): RedirectResponse
    {
        $this->autorizar();

        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|in:'.implode(',', array_keys(ProgramaRitmo::años())),
            'vigente' => 'nullable|boolean',
            'en_programa' => 'nullable|boolean',
            'estado_nota' => 'nullable|string|max:500',
        ], [
            'nombre.required' => 'El toque necesita un nombre.',
            'año.in' => 'Elegí un año del programa.',
        ]);

        $cambios = $gestion->actualizar($programaRitmo, [
            'nombre' => $datos['nombre'],
            'año' => (int) $datos['año'],
            'vigente' => $request->boolean('vigente'),
            'en_programa' => $request->boolean('en_programa'),
            'estado_nota' => $datos['estado_nota'] ?? null,
        ]);

        $volver = redirect()
            ->route('programa.gestion', array_filter($request->only(['estado', 'q'])))
            ->withFragment('toque-'.$programaRitmo->id);

        return $cambios === []
            ? $volver->with('success', "«{$programaRitmo->nombre}»: no había cambios.")
            : $volver->with('success', "«{$programaRitmo->nombre}» quedó actualizado.");
    }

    /** Solo administración y superadministración. */
    private function autorizar(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
