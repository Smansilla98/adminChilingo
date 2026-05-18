<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use App\Support\ProgramaRitmoSlug;
use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class ProgramaController extends Controller
{
    public function index()
    {
        $años = ProgramaRitmo::años();
        $porAño = collect();
        $secciones = collect();
        $seccionesPorCategoria = collect();
        $estadoPrograma = 'ok';
        $totalRitmos = 0;

        if (! Schema::hasTable('programa_ritmos')) {
            $estadoPrograma = 'sin_tabla';

            return view('programa.index', compact(
                'porAño', 'años', 'secciones', 'seccionesPorCategoria', 'estadoPrograma', 'totalRitmos'
            ));
        }

        try {
            $this->asegurarDatosBase();

            $ritmos = ProgramaRitmo::query()
                ->where('publicado', true)
                ->orderBy('año')
                ->orderBy('orden')
                ->get();
            $totalRitmos = $ritmos->count();
            $porAño = $ritmos->groupBy(fn (ProgramaRitmo $r) => (int) $r->año);

            if ($totalRitmos === 0) {
                $estadoPrograma = 'vacio';
            }

            if (Schema::hasTable('programa_secciones')) {
                $secciones = ProgramaSeccion::query()
                    ->where('activo', true)
                    ->orderBy('orden')
                    ->get();
                $seccionesPorCategoria = $secciones->groupBy('categoria');
            }
        } catch (QueryException $e) {
            report($e);
            $estadoPrograma = 'error';
        }

        return view('programa.index', compact(
            'porAño', 'años', 'secciones', 'seccionesPorCategoria', 'estadoPrograma', 'totalRitmos'
        ));
    }

    public function showToque(ProgramaRitmo $programaRitmo)
    {
        if (! $programaRitmo->publicado && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

        $años = ProgramaRitmo::años();
        $vecinos = ProgramaRitmo::query()
            ->where('publicado', true)
            ->orderBy('año')
            ->orderBy('orden')
            ->get();

        $idx = $vecinos->search(fn ($r) => $r->id === $programaRitmo->id);
        $anterior = $idx !== false && $idx > 0 ? $vecinos[$idx - 1] : null;
        $siguiente = $idx !== false && $idx < $vecinos->count() - 1 ? $vecinos[$idx + 1] : null;

        $objetivosAnio = null;
        if (Schema::hasTable('programa_secciones')) {
            $objetivosAnio = ProgramaSeccion::query()
                ->where('activo', true)
                ->where('categoria', ProgramaSeccion::CAT_ANIO)
                ->where(function ($q) use ($programaRitmo) {
                    $q->where('anio', $programaRitmo->año)
                        ->orWhere('slug', 'objetivos-anio-5-7');
                })
                ->orderBy('orden')
                ->first();
        }

        return view('programa.toque', compact('programaRitmo', 'años', 'anterior', 'siguiente', 'objetivosAnio'));
    }

    public function editToque(ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        return view('programa.toque-edit', compact('programaRitmo'));
    }

    public function updateToque(Request $request, ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'autor' => 'nullable|string|max:500',
            'notas' => 'nullable|string|max:500',
            'resumen' => 'nullable|string|max:2000',
            'contenido' => 'nullable|string|max:50000',
            'opcional' => 'nullable|boolean',
            'publicado' => 'nullable|boolean',
            'secciones' => 'nullable|array',
            'secciones.*.titulo' => 'nullable|string|max:255',
            'secciones.*.contenido' => 'nullable|string|max:20000',
            'enlaces' => 'nullable|array',
            'enlaces.*.etiqueta' => 'nullable|string|max:120',
            'enlaces.*.url' => 'nullable|url|max:500',
        ]);

        $secciones = collect($validated['secciones'] ?? [])
            ->map(fn ($s) => [
                'titulo' => trim((string) ($s['titulo'] ?? '')),
                'contenido' => trim((string) ($s['contenido'] ?? '')),
            ])
            ->filter(fn ($s) => $s['titulo'] !== '' || $s['contenido'] !== '')
            ->values()
            ->all();

        $enlaces = collect($validated['enlaces'] ?? [])
            ->map(fn ($e) => [
                'etiqueta' => trim((string) ($e['etiqueta'] ?? '')),
                'url' => trim((string) ($e['url'] ?? '')),
            ])
            ->filter(fn ($e) => $e['url'] !== '')
            ->values()
            ->all();

        $programaRitmo->update([
            'nombre' => $validated['nombre'],
            'autor' => $validated['autor'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'resumen' => $validated['resumen'] ?? null,
            'contenido' => $validated['contenido'] ?? null,
            'opcional' => $request->boolean('opcional'),
            'publicado' => $request->boolean('publicado'),
            'secciones' => $secciones !== [] ? $secciones : null,
            'enlaces' => $enlaces !== [] ? $enlaces : null,
        ]);

        return redirect()
            ->route('programa.toque.show', $programaRitmo)
            ->with('success', 'Profundización del toque actualizada.');
    }

    public function editSeccion(ProgramaSeccion $programaSeccion)
    {
        $this->authorizeAdmin();

        return view('programa.seccion-edit', [
            'seccion' => $programaSeccion,
            'categorias' => ProgramaSeccion::categorias(),
        ]);
    }

    public function updateSeccion(Request $request, ProgramaSeccion $programaSeccion)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'subtitulo' => 'nullable|string|max:500',
            'cuerpo' => 'nullable|string|max:100000',
            'activo' => 'nullable|boolean',
        ]);

        $programaSeccion->update([
            'titulo' => $validated['titulo'],
            'subtitulo' => $validated['subtitulo'] ?? null,
            'cuerpo' => $validated['cuerpo'] ?? null,
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()
            ->route('programa.index', ['seccion' => $programaSeccion->slug])
            ->with('success', 'Sección del programa actualizada.');
    }

    private function asegurarDatosBase(): void
    {
        if (! ProgramaRitmo::query()->exists()) {
            ProgramaRitmosSeeder::poblarSiVacio();
        }
        ProgramaRitmosSeeder::asegurarSlugs();

        if (Schema::hasTable('programa_secciones') && ! ProgramaSeccion::query()->exists()) {
            ProgramaSeccionesSeeder::poblarSiVacio();
        }
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }
}
