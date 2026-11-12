<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ControlaToquesPrograma;
use App\Models\ProgramaRitmo;
use App\Services\PartiturasCuadernilloImporter;
use App\Services\ProgramaRitmoMediosService;
use App\Support\AccesoMaterial;
use App\Support\ProgramaArranque;
use App\Support\ProgramaRitmoMedios;
use App\Support\ProgramaRitmoSlug;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Partituras del programa (admin): catálogo, carga de PDF, cuadernillo y alta de toques.
 */
class ProgramaPartiturasController extends Controller
{
    use ControlaToquesPrograma;

    /**
     * Catálogo de toques con estado de partituras y recursos multimedia.
     */
    public function partiturasIndex(Request $request)
    {
        // Las partituras son material: piden cuenta (el programa sigue público).
        if (! $request->user()) {
            return redirect()->guest(route('login'))
                ->with('info', 'Para ver las partituras entrá con tu cuenta. Si sos alumno y no tenés, podés crearla con tu DNI.');
        }
        $anioMaximo = AccesoMaterial::anioMaximo($request->user());

        $años = ProgramaRitmo::años();
        $porAño = collect();
        $estadoPrograma = 'ok';
        $busqueda = trim((string) $request->query('q', ''));
        $pendientes = (int) $request->query('pendientes', 0) === 1;
        $digital = (int) $request->query('digital', 0) === 1;
        $crear = (int) $request->query('crear', 0) === 1;

        if (! Schema::hasTable('programa_ritmos')) {
            $estadoPrograma = 'sin_tabla';

            return view('programa.partituras', compact('porAño', 'años', 'estadoPrograma', 'busqueda', 'pendientes', 'digital', 'crear', 'anioMaximo'));
        }

        try {
            ProgramaArranque::asegurar();

            $q = ProgramaRitmo::soloEnPrograma(ProgramaRitmo::query());
            if (Schema::hasColumn('programa_ritmos', 'publicado') && ! auth()->user()?->isAdmin()) {
                $q->where('publicado', true);
            }
            if ($anioMaximo !== AccesoMaterial::SIN_LIMITE) {
                $q->where('año', '<=', $anioMaximo);
            }
            $ritmos = ProgramaRitmo::traerOrdenados($q);
            if ($busqueda !== '') {
                // También por nombre anterior: un toque renombrado se sigue encontrando.
                $buscado = mb_strtolower($busqueda);
                $ritmos = $ritmos->filter(fn (ProgramaRitmo $r) => str_contains(mb_strtolower($r->nombre), $buscado)
                    || str_contains(mb_strtolower((string) $r->autor), $buscado)
                    || collect($r->historialNombres())->contains(fn ($n) => str_contains(mb_strtolower($n['nombre']), $buscado)));
            }
            $ritmos = $ritmos->map(function (ProgramaRitmo $r) {
                $m = $r->mediosNormalizados();
                $tieneArchivo = ! empty($m['partitura']['path']);
                $videosBase = collect($m['videos_base'] ?? [])->filter(fn ($v) => ! empty($v['url']))->count();
                $videosGrupo = collect($m['videos_grupo'] ?? [])->filter(fn ($v) => ! empty($v['url']))->count();
                $r->resumen_medios = [
                    'partitura' => $tieneArchivo,
                    'partitura_nombre' => $m['partitura']['nombre'] ?? null,
                    'digital' => \App\Support\PartituraScore::tieneGolpes($m['partitura_score'] ?? null),
                    'videos' => $videosBase + $videosGrupo,
                    'cortes' => count($m['cortes'] ?? []),
                    'recursos' => count($m['recursos'] ?? []),
                ];

                return $r;
            });
            if ($pendientes) {
                $ritmos = $ritmos->filter(fn (ProgramaRitmo $r) => empty(($r->resumen_medios ?? [])['partitura']));
            }
            if ($digital) {
                $ritmos = $ritmos->filter(fn (ProgramaRitmo $r) => ! empty(($r->resumen_medios ?? [])['digital']));
            }
            if ($crear) {
                $ritmos = $ritmos->filter(fn (ProgramaRitmo $r) => empty(($r->resumen_medios ?? [])['digital']));
            }
            $porAño = $ritmos->groupBy(fn (ProgramaRitmo $r) => (int) $r->año);
            if ($ritmos->isEmpty()) {
                $estadoPrograma = 'vacio';
            }
        } catch (QueryException $e) {
            report($e);
            $estadoPrograma = 'error';
        }

        return view('programa.partituras', compact('porAño', 'años', 'estadoPrograma', 'busqueda', 'pendientes', 'digital', 'crear', 'anioMaximo'));
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
            'abrir_editor' => 'nullable|boolean',
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

        if ($request->boolean('abrir_editor')) {
            return redirect()
                ->route('programa.toque.editor', $programaRitmo)
                ->with('success', 'Original guardado. Está al lado del editor para pasarlo a partitura digital.');
        }

        return redirect()
            ->route('programa.partituras.index')
            ->with('success', 'Partitura de «'.$programaRitmo->nombre.'» guardada.');
    }

    /**
     * Alta de un toque nuevo (admin) y entra al editor vacío para crear la partitura.
     */
    public function storeToque(Request $request)
    {
        $this->authorizeAdmin();

        if (! Schema::hasTable('programa_ritmos')) {
            return redirect()
                ->route('programa.partituras.index')
                ->with('error', 'Falta migrar programa_ritmos.');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:1|max:7',
            'autor' => 'nullable|string|max:500',
        ]);

        $año = (int) $validated['año'];
        $orden = (int) ProgramaRitmo::query()->where('año', $año)->max('orden') + 1;
        $slug = ProgramaRitmoSlug::generar($año, $validated['nombre']);

        $attrs = [
            'año' => $año,
            'orden' => max(1, $orden),
            'nombre' => $validated['nombre'],
            'autor' => $validated['autor'] ?? null,
        ];
        if (Schema::hasColumn('programa_ritmos', 'slug')) {
            $attrs['slug'] = $slug;
        }
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $attrs['publicado'] = true;
        }
        if (Schema::hasColumn('programa_ritmos', 'medios')) {
            $attrs['medios'] = ProgramaRitmoMedios::estructuraVacia();
        }

        $toque = ProgramaRitmo::query()->create($attrs);

        return redirect()
            ->route('programa.toque.editor', $toque)
            ->with('success', 'Toque creado. Indicá tu nombre y escribí la partitura (o importá MusicXML).');
    }

    /**
     * Asigna a cada toque el PDF del Cuadernillo (páginas correspondientes).
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

        ProgramaArranque::asegurar();

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
}
