<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use App\Support\ArchivoSeguro;
use App\Support\ProgramaArranque;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Programa público: índice por año, ficha de cada toque y descarga de sus archivos.
 */
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
            ProgramaArranque::asegurar();

            $qRitmos = ProgramaRitmo::soloEnPrograma(ProgramaRitmo::query());
            if (Schema::hasColumn('programa_ritmos', 'publicado') && ! auth()->user()?->isAdmin()) {
                $qRitmos->where('publicado', true);
            }
            $ritmos = ProgramaRitmo::traerOrdenados($qRitmos);
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
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

        $años = ProgramaRitmo::años();
        $qVecinos = ProgramaRitmo::soloEnPrograma(ProgramaRitmo::query())->orderBy('año')->orderBy('orden');
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

        $bibliotecaItems = collect();
        if (Schema::hasTable('biblioteca_items')
            && Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
            $bibliotecaItems = BibliotecaItem::query()
                ->publicados()
                ->deToque($programaRitmo)
                ->with(['tags', 'toque'])
                ->latest()
                ->limit(24)
                ->get();
        }

        return view('programa.toque', compact(
            'programaRitmo',
            'años',
            'anterior',
            'siguiente',
            'objetivosAnio',
            'medios',
            'bibliotecaItems'
        ));
    }

    public function descargarMedio(ProgramaRitmo $programaRitmo, Request $request): StreamedResponse
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

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

        return ArchivoSeguro::responder('comprobantes', $path, $nombre, $request->boolean('inline'));
    }
}
