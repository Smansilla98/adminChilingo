<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\CobroCuotasService;
use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Support\FichasAlumno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/** Alumno: pagar sus cuotas desde el sistema (transferencia al alias o Mercado Pago). */
class CobroCuotaController extends Controller
{
    public function index(Request $request, CobroCuotasService $cobros): View
    {
        $fichas = $this->fichas($request);
        if ($fichas->isEmpty()) {
            return $this->sinFicha($request);
        }
        // Sin elegir, se abre la primera ficha que tenga algo para pagar: una cuenta puede
        // tener una ficha duplicada sin bloques y no tiene que tapar a la real.
        $alumno = $fichas->firstWhere('id', (int) $request->query('alumno'))
            ?? $fichas->first(fn (Alumno $a) => $cobros->pendientes($a)->isNotEmpty())
            ?? $fichas->first(fn (Alumno $a) => $a->bloques->isNotEmpty() || $a->bloque_id)
            ?? $fichas->first();

        return view('alumno.pagar-cuotas', [
            'fichas' => $fichas,
            'alumno' => $alumno,
            'pendientes' => $cobros->pendientes($alumno),
            'resumen' => $cobros->resumen($alumno),
            'opcionesExtra' => $cobros->opcionesExtra($alumno),
            'transferencia' => $cobros->datosTransferencia(),
            'mercadoPago' => $cobros->mercadoPagoDisponible(),
            'enCurso' => ComprobanteCuotaAlumno::query()
                ->whereIn('alumno_id', $fichas->pluck('id'))
                ->where('estado', 'esperando_pago')
                ->latest()->get(),
        ]);
    }

    public function store(Request $request, CobroCuotasService $cobros): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer'],
            'cuota_ids' => ['required', 'array', 'min:1'],
            'cuota_ids.*' => ['integer'],
            'medio' => ['required', 'in:'.implode(',', CobroCuotasService::MEDIOS)],
        ], ['cuota_ids.required' => 'Marcá qué cuotas vas a pagar.', 'medio.required' => 'Elegí cómo vas a pagar.']);
        $alumno = $this->fichas($request)->firstWhere('id', (int) $datos['alumno_id']);
        abort_unless($alumno instanceof Alumno, 403);

        $pago = $cobros->iniciar($request->user(), $alumno, $datos['cuota_ids'], $datos['medio']);

        return $this->continuar($pago, $datos['medio'], $request, $cobros);
    }

    /** Pagar igual un bloque y un mes que no figura entre las cuotas pendientes. */
    public function extra(Request $request, CobroCuotasService $cobros): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer'],
            'bloque_id' => ['required', 'integer'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'monto' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'concepto' => ['nullable', 'string', 'max:200'],
            'medio' => ['required', 'in:'.implode(',', CobroCuotasService::MEDIOS)],
        ], [
            'bloque_id.required' => 'Elegí el bloque.',
            'monto.required' => 'Ingresá el monto a pagar.',
            'monto.min' => 'Ingresá el monto a pagar.',
            'medio.required' => 'Elegí cómo vas a pagar.',
        ]);
        $alumno = $this->fichas($request)->firstWhere('id', (int) $datos['alumno_id']);
        abort_unless($alumno instanceof Alumno, 403);

        $pago = $cobros->iniciarExtra($request->user(), $alumno, (int) $datos['bloque_id'], (int) $datos['anio'], (int) $datos['mes'], (float) $datos['monto'], $datos['medio'], $datos['concepto'] ?? null);

        return $this->continuar($pago, $datos['medio'], $request, $cobros);
    }

    private function continuar(ComprobanteCuotaAlumno $pago, string $medio, Request $request, CobroCuotasService $cobros): RedirectResponse
    {
        if ($medio === 'mercadopago') {
            try {
                return redirect()->away($cobros->linkMercadoPago($pago, $request->user()->email));
            } catch (RuntimeException $e) {
                $cobros->cancelar($pago);

                return back()->withErrors(['medio' => $e->getMessage()]);
            }
        }

        return redirect()->route('alumno.cuota.pago', $pago->referencia);
    }

    public function show(Request $request, string $referencia, CobroCuotasService $cobros): View
    {
        $pago = $this->pagoPropio($request, $referencia);
        $pago->load(['items.cuota', 'items.bloque.sede', 'items.bloque.profesor', 'items.bloque.profesores', 'alumno']);

        return view('alumno.pago-cuotas', [
            'pago' => $pago,
            'transferencia' => $cobros->datosTransferencia(),
            'vuelveDeMercadoPago' => $request->filled('collection_status') || $request->filled('status'),
            'estadoMercadoPago' => $request->query('collection_status') ?? $request->query('status'),
        ]);
    }

    public function comprobante(Request $request, string $referencia, CobroCuotasService $cobros): RedirectResponse
    {
        $pago = $this->pagoPropio($request, $referencia);
        $datos = $request->validate([
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'comprobante.required' => 'Falta la foto o el PDF del comprobante.',
            'comprobante.mimes' => 'El comprobante tiene que ser una foto (JPG o PNG) o un PDF.',
            'comprobante.max' => 'El archivo es muy pesado (hasta 10 MB).',
        ]);
        $cobros->adjuntarComprobante($pago, $request->file('comprobante'), $datos['fecha_pago']);

        return redirect()->route('alumno.cuota.pago', $referencia)->with('success', '¡Listo! Recibimos el comprobante. Cuando administración lo confirme, la cuota queda registrada.');
    }

    public function cancelar(Request $request, string $referencia, CobroCuotasService $cobros): RedirectResponse
    {
        $cobros->cancelar($this->pagoPropio($request, $referencia));

        return redirect()->route('alumno.cuota.pagar')->with('success', 'Cancelaste ese pago. Las cuotas vuelven a quedar para pagar.');
    }

    private function pagoPropio(Request $request, string $referencia): ComprobanteCuotaAlumno
    {
        $pago = ComprobanteCuotaAlumno::query()->where('referencia', $referencia)->firstOrFail();
        abort_unless($this->fichas($request)->contains('id', $pago->alumno_id), 404);

        return $pago;
    }

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
