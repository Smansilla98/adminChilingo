<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\ComprobanteService;
use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Support\FichasAlumno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Mi cuota": el alumno con cuenta sube su comprobante. Ya sabe quién es, en qué bloques
 * cursa y cuánto es la cuota del mes: no tiene que buscarse en ninguna lista.
 */
class MiCuotaController extends Controller
{
    public function create(Request $request, ComprobanteService $comprobantes): View
    {
        $fichas = $this->fichas($request);
        if ($fichas->isEmpty()) {
            return $this->sinFicha($request);
        }

        $alumno = $fichas->firstWhere('id', (int) $request->query('alumno')) ?? $fichas->first();
        $anio = (int) $request->query('anio', now()->year);
        $mes = (int) $request->query('mes', now()->month);
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            [$anio, $mes] = [now()->year, now()->month];
        }

        return view('alumno.mi-cuota', [
            'fichas' => $fichas,
            'alumno' => $alumno,
            'anio' => $anio,
            'mes' => $mes,
            'opciones' => $comprobantes->opciones($alumno, $anio, $mes),
            'enviados' => ComprobanteCuotaAlumno::query()
                ->whereIn('alumno_id', $fichas->pluck('id'))
                ->latest()->limit(6)->get(),
            'estados' => ComprobanteService::ESTADOS,
        ]);
    }

    public function store(Request $request, ComprobanteService $comprobantes): RedirectResponse
    {
        $fichas = $this->fichas($request);
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'bloque_ids' => ['required', 'array', 'min:1'],
            'bloque_ids.*' => ['integer'],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'bloque_ids.required' => 'Marcá qué cuota estás pagando.',
            'comprobante.required' => 'Falta la foto o el PDF del comprobante.',
            'comprobante.mimes' => 'El comprobante tiene que ser una foto (JPG o PNG) o un PDF.',
            'comprobante.max' => 'El archivo es muy pesado (hasta 10 MB). Probá sacarle una foto de nuevo o mandá un PDF.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
        ]);

        $alumno = $fichas->firstWhere('id', (int) $datos['alumno_id']);
        abort_unless($alumno instanceof Alumno, 403);

        $comprobantes->enviarPropio(
            $request->user(), $alumno, (int) $datos['anio'], (int) $datos['mes'], $datos['fecha_pago'],
            array_map('intval', $datos['bloque_ids']), $request->file('comprobante'), $datos['notas'] ?? null,
        );

        return redirect()->route('alumno.cuota', ['anio' => $datos['anio'], 'mes' => $datos['mes']])
            ->with('success', '¡Listo! Recibimos tu comprobante. Tu profe o administración lo van a revisar; acá abajo vas a ver cuando quede registrado.');
    }

    /** @return Collection<int, Alumno> fichas de alumno de la persona logueada */

    /** Cuenta sin ficha de alumno: explica qué pasa y, a quien administra, qué ficha unir. */
    private function sinFicha(Request $request): View
    {
        $user = $request->user();
        $puedeUnir = $user->can('personas.merge') || $user->isAdmin();

        return view('alumno.sin-ficha', [
            'user' => $user,
            'puedeUnir' => $puedeUnir,
            'candidatas' => $puedeUnir ? FichasAlumno::candidatas($user) : collect(),
        ]);
    }

    private function fichas(Request $request): Collection
    {
        return FichasAlumno::delUsuario($request->user());
    }
}
