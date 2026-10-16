<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\CobroCuotasService;
use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
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
        abort_if($fichas->isEmpty(), 403, 'Tu cuenta no está vinculada a una ficha de alumno. Pedile a administración que la vincule.');
        $alumno = $fichas->firstWhere('id', (int) $request->query('alumno')) ?? $fichas->first();

        return view('alumno.pagar-cuotas', [
            'fichas' => $fichas,
            'alumno' => $alumno,
            'pendientes' => $cobros->pendientes($alumno),
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
        if ($datos['medio'] === 'mercadopago') {
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

    private function fichas(Request $request): Collection
    {
        $user = $request->user();

        return Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->when($user->persona_id, fn ($q2) => $q2->orWhere('persona_id', $user->persona_id)))
            ->with(['bloques', 'bloque', 'sede'])
            ->get();
    }
}
