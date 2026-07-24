<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use App\Services\PartiturasCuadernilloImporter;
use App\Services\ProgramaRitmoMediosService;
use App\Support\ProgramaRitmoMedios;
use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            $qRitmos = ProgramaRitmo::query()->orderBy('año')->orderBy('orden');
            if (Schema::hasColumn('programa_ritmos', 'publicado')) {
                $qRitmos->where('publicado', true);
            }
            $ritmos = $qRitmos->get();
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

    /**
     * Catálogo de toques con estado de partituras y recursos multimedia.
     */
    public function partiturasIndex(Request $request)
    {
        $años = ProgramaRitmo::años();
        $porAño = collect();
        $estadoPrograma = 'ok';
        $busqueda = trim((string) $request->query('q', ''));
        $pendientes = (int) $request->query('pendientes', 0) === 1;

        if (! Schema::hasTable('programa_ritmos')) {
            $estadoPrograma = 'sin_tabla';

            return view('programa.partituras', compact('porAño', 'años', 'estadoPrograma', 'busqueda', 'pendientes'));
        }

        try {
            $this->asegurarDatosBase();

            $q = ProgramaRitmo::query()->orderBy('año')->orderBy('orden');
            if (Schema::hasColumn('programa_ritmos', 'publicado') && ! auth()->user()?->isAdmin()) {
                $q->where('publicado', true);
            }
            if ($busqueda !== '') {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nombre', 'like', '%'.$busqueda.'%')
                        ->orWhere('autor', 'like', '%'.$busqueda.'%');
                });
            }
            $ritmos = $q->get()->map(function (ProgramaRitmo $r) {
                $m = $r->mediosNormalizados();
                $tieneArchivo = ! empty($m['partitura']['path']);
                $videosBase = collect($m['videos_base'] ?? [])->filter(fn ($v) => ! empty($v['url']))->count();
                $videosGrupo = collect($m['videos_grupo'] ?? [])->filter(fn ($v) => ! empty($v['url']))->count();
                $r->resumen_medios = [
                    'partitura' => $tieneArchivo,
                    'partitura_nombre' => $m['partitura']['nombre'] ?? null,
                    'digital' => ! empty($m['partitura_vexflow']['sections']) || ! empty($m['partitura_vexflow']['hits']),
                    'videos' => $videosBase + $videosGrupo,
                    'cortes' => count($m['cortes'] ?? []),
                    'recursos' => count($m['recursos'] ?? []),
                ];

                return $r;
            });
            if ($pendientes) {
                $ritmos = $ritmos->filter(fn (ProgramaRitmo $r) => empty(($r->resumen_medios ?? [])['partitura']));
            }
            $porAño = $ritmos->groupBy(fn (ProgramaRitmo $r) => (int) $r->año);
            if ($ritmos->isEmpty()) {
                $estadoPrograma = 'vacio';
            }
        } catch (QueryException $e) {
            report($e);
            $estadoPrograma = 'error';
        }

        return view('programa.partituras', compact('porAño', 'años', 'estadoPrograma', 'busqueda', 'pendientes'));
    }

    public function editPartitura(ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $medios = $programaRitmo->mediosNormalizados();
        $años = ProgramaRitmo::años();

        return view('programa.partitura-edit', compact('programaRitmo', 'medios', 'años'));
    }

    public function updatePartitura(Request $request, ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $request->validate([
            'quitar_partitura' => 'nullable|boolean',
            'partitura_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
        ]);

        if (! $request->boolean('quitar_partitura') && ! $request->hasFile('partitura_archivo')) {
            $actual = $programaRitmo->mediosNormalizados();
            if (empty($actual['partitura']['path'])) {
                return back()->withErrors(['partitura_archivo' => 'Elegí un PDF o una imagen para subir.'])->withInput();
            }
        }

        if (Schema::hasColumn('programa_ritmos', 'medios')) {
            $programaRitmo->update([
                'medios' => app(ProgramaRitmoMediosService::class)->actualizarSoloPartitura($request, $programaRitmo),
            ]);
        }

        return redirect()
            ->route('programa.partituras.index')
            ->with('success', 'Partitura de «'.$programaRitmo->nombre.'» guardada.');
    }

    public function editCompositor(ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $medios = $programaRitmo->mediosNormalizados();

        return view('programa.compositor-edit', compact('programaRitmo', 'medios'));
    }

    public function updateCompositor(Request $request, ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $request->validate([
            'quitar_partitura_vexflow' => 'nullable|boolean',
            'partitura_vexflow_json' => 'nullable|string|max:500000',
        ]);

        if (! $request->boolean('quitar_partitura_vexflow')) {
            $json = trim((string) $request->input('partitura_vexflow_json', ''));
            $actual = $programaRitmo->mediosNormalizados();
            $decoded = $json !== '' ? json_decode($json, true) : null;
            $normalizada = is_array($decoded)
                ? ProgramaRitmoMedios::normalizarPartituraVexflow($decoded)
                : null;

            if ($normalizada === null && empty($actual['partitura_vexflow'])) {
                return back()->withErrors([
                    'partitura_vexflow_json' => 'La partitura está vacía. Cargá al menos un golpe o usá el ejemplo antes de guardar.',
                ]);
            }
        }

        if (Schema::hasColumn('programa_ritmos', 'medios')) {
            $programaRitmo->update([
                'medios' => app(ProgramaRitmoMediosService::class)->actualizarSoloCompositor($request, $programaRitmo),
            ]);
        }

        return redirect()
            ->route('programa.toque.show', $programaRitmo)
            ->with('success', 'Partitura digital de «'.$programaRitmo->nombre.'» guardada.');
    }

    public function showToque(ProgramaRitmo $programaRitmo)
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

        $años = ProgramaRitmo::años();
        $qVecinos = ProgramaRitmo::query()->orderBy('año')->orderBy('orden');
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $qVecinos->where('publicado', true);
        }
        $vecinos = $qVecinos->get();

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

        $medios = $programaRitmo->mediosNormalizados();

        return view('programa.toque', compact('programaRitmo', 'años', 'anterior', 'siguiente', 'objetivosAnio', 'medios'));
    }

    public function editToque(ProgramaRitmo $programaRitmo)
    {
        $this->authorizeAdmin();

        $medios = $programaRitmo->mediosNormalizados();

        return view('programa.toque-edit', [
            'programaRitmo' => $programaRitmo,
            'medios' => $medios,
            'videosBase' => ProgramaRitmoMedios::VIDEOS_BASE,
            'videosGrupo' => ProgramaRitmoMedios::VIDEOS_GRUPO,
            'tiposRecurso' => ProgramaRitmoMedios::TIPOS_RECURSO,
        ]);
    }

    public function descargarMedio(ProgramaRitmo $programaRitmo, Request $request): StreamedResponse
    {
        $tipo = (string) $request->query('tipo', '');
        $index = $request->integer('i');
        $medios = $programaRitmo->mediosNormalizados();
        $path = null;
        $nombre = 'archivo';

        if ($tipo === 'partitura' && ! empty($medios['partitura']['path'])) {
            $path = $medios['partitura']['path'];
            $nombre = $medios['partitura']['nombre'] ?? 'partitura-'.$programaRitmo->slug;
        } elseif ($tipo === 'corte' && isset($medios['cortes'][$index]['path'])) {
            $path = $medios['cortes'][$index]['path'];
            $nombre = $medios['cortes'][$index]['nombre'] ?? 'corte-'.$index;
        } elseif ($tipo === 'recurso' && isset($medios['recursos'][$index]['path'])) {
            $path = $medios['recursos'][$index]['path'];
            $nombre = $medios['recursos'][$index]['nombre'] ?? 'recurso-'.$index;
        }

        if (! $path || ! Storage::disk('comprobantes')->exists($path)) {
            abort(404);
        }

        if ($request->boolean('inline')) {
            return Storage::disk('comprobantes')->response($path, $nombre, [
                'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
            ]);
        }

        return Storage::disk('comprobantes')->download($path, $nombre);
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
            'enlaces.*.url' => 'nullable|string|max:500',
            'quitar_partitura' => 'nullable|boolean',
            'partitura_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
            'videos_base' => 'nullable|array',
            'videos_base.*.url' => 'nullable|string|max:500',
            'videos_grupo' => 'nullable|array',
            'videos_grupo.*.url' => 'nullable|string|max:500',
            'cortes' => 'nullable|array',
            'cortes.*.titulo' => 'nullable|string|max:255',
            'cortes.*.url' => 'nullable|string|max:500',
            'cortes.*.archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,mp4,webm|max:51200',
            'recursos' => 'nullable|array',
            'recursos.*.tipo' => 'nullable|string|max:32',
            'recursos.*.titulo' => 'nullable|string|max:255',
            'recursos.*.url' => 'nullable|string|max:500',
            'recursos.*.contenido' => 'nullable|string|max:20000',
            'recursos.*.archivo' => 'nullable|file|max:51200',
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

        $update = [
            'nombre' => $validated['nombre'],
            'autor' => $validated['autor'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'resumen' => $validated['resumen'] ?? null,
            'contenido' => $validated['contenido'] ?? null,
            'opcional' => $request->boolean('opcional'),
            'publicado' => $request->boolean('publicado'),
            'secciones' => $secciones !== [] ? $secciones : null,
            'enlaces' => $enlaces !== [] ? $enlaces : null,
        ];

        if (Schema::hasColumn('programa_ritmos', 'medios')) {
            $update['medios'] = app(ProgramaRitmoMediosService::class)->procesarDesdeRequest($request, $programaRitmo);
        }

        $programaRitmo->update($update);

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

    /**
     * Carga en la BD las partituras digitales del Cuadernillo (JSON → partitura_vexflow).
     * Equivalente a: php artisan db:seed --class=PartiturasCuadernilloSeeder
     */
    public function importarCuadernillo(Request $request, PartiturasCuadernilloImporter $importer)
    {
        $this->authorizeAdmin();

        if (! Schema::hasTable('programa_ritmos')) {
            return redirect()
                ->route('programa.partituras.index')
                ->with('error', 'La tabla de ritmos no existe. Corré las migraciones primero.');
        }

        $this->asegurarDatosBase();

        try {
            $result = $importer->importar();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('programa.partituras.index')
                ->with('error', 'Error al cargar el cuadernillo: '.$e->getMessage());
        }

        $msg = "PDFs del cuadernillo: {$result['ok']} toques actualizados";
        if ($result['created'] > 0) {
            $msg .= ", {$result['created']} toques nuevos";
        }
        if ($result['fail'] > 0) {
            $msg .= ", {$result['fail']} con error";
        }
        $msg .= '.';

        $redirect = redirect()->route('programa.partituras.index');
        if ($result['ok'] > 0) {
            $redirect->with('success', $msg);
        } else {
            $extra = $result['messages'][0] ?? '';
            $redirect->with('error', trim($msg.' '.$extra));
        }

        return $redirect;
    }

    private function asegurarDatosBase(): void
    {
        if (! ProgramaRitmo::query()->exists()) {
            ProgramaRitmosSeeder::poblarSiVacio();
        }
        if (Schema::hasColumn('programa_ritmos', 'slug')) {
            ProgramaRitmosSeeder::asegurarSlugs();
        }

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
