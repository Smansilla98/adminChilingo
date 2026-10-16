<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use App\Models\Cuota;
use App\Models\Sede;
use App\Models\User;
use App\Services\PagoDesdeComprobanteService;
use App\Support\LiquidacionDocente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type LineaCobro array{orden: string, cuota_id: int, cuota: string, periodo: string, vencida: bool, monto: float, beca: string|null, bloque_id: int|null, bloque: string|null, sede: string|null, profesor: string|null, abono_profesor: float, en_curso: string|null}
 *
 * Pago de cuotas iniciado desde el sistema.
 *
 * El alumno elige las cuotas que debe (de cualquier bloque, sede o mes) y el sistema
 * crea un comprobante "esperando pago" con un código de referencia. Se completa:
 * - por transferencia: el alumno paga al alias y sube el comprobante; administración aprueba;
 * - por Mercado Pago: el aviso de pago aprobado lo registra solo.
 * Al aprobarse, PagoDesdeComprobanteService reparte el pago por cuota y calcula lo que le
 * corresponde a cada docente según el porcentaje de liquidación de la sede.
 */
class CobroCuotasService
{
    public const MEDIOS = ['transferencia', 'mercadopago'];

    public function __construct(
        private EstadoCuentaService $estadoCuenta,
        private PagoDesdeComprobanteService $pagos,
        private MercadoPagoClient $mercadoPago,
    ) {}

    /** Datos de la cuenta a la que se transfiere (config chilinga.cobro). */
    public function datosTransferencia(): ?array
    {
        $datos = array_filter(config('chilinga.cobro', []), fn ($v) => filled($v));

        return isset($datos['alias']) || isset($datos['cbu']) ? $datos : null;
    }

    public function mercadoPagoDisponible(): bool
    {
        return $this->mercadoPago->configurado();
    }

    /**
     * Cuotas que el alumno puede pagar desde el sistema: año actual y anterior, con saldo,
     * sin pagos parciales y sin otro pago iniciado en curso. Cada una con su bloque, sede,
     * docente y lo que le corresponde al docente.
     *
     * @return Collection<int, LineaCobro>
     */
    public function pendientes(Alumno $alumno): Collection
    {
        $alumno->loadMissing(['bloques.sede', 'bloques.profesor', 'bloques.profesores', 'bloque.sede', 'bloque.profesor', 'bloque.profesores']);
        $bloques = $alumno->bloques->keyBy('id');
        if ($alumno->bloque && ! $bloques->has($alumno->bloque->id)) {
            $bloques->put($alumno->bloque->id, $alumno->bloque);
        }
        $bloques = $bloques->filter(fn (Bloque $b) => $b->activo);

        $items = collect([now()->year - 1, now()->year])
            ->flatMap(fn (int $anio) => $this->estadoCuenta->paraAlumno($alumno, $anio)['items'])
            ->filter(fn (array $i) => $i['saldo'] > 0 && $i['pagado'] <= 0);
        if ($items->isEmpty()) {
            return collect();
        }

        $cuotas = Cuota::query()->with(['bloque.sede', 'sede'])->whereIn('id', $items->pluck('cuota_id'))->get()->keyBy('id');
        $enCurso = ComprobanteCuotaAlumnoItem::query()
            ->whereIn('cuota_id', $items->pluck('cuota_id'))
            ->whereHas('comprobante', fn ($q) => $q->where('alumno_id', $alumno->id)->whereIn('estado', ['esperando_pago', 'pendiente', 'visto'])->whereNull('pago_id'))
            ->with('comprobante:id,referencia,estado')
            ->get()
            ->keyBy('cuota_id');

        $lineas = [];
        foreach ($items as $i) {
            $cuota = $cuotas->get($i['cuota_id']);
            if (! $cuota) {
                continue;
            }
            $bloque = $this->bloqueDeCuota($cuota, $bloques);
            $sede = $bloque?->sede;
            if (! $sede instanceof Sede) {
                $sede = LiquidacionDocente::sedeParaCuota($cuota);
            }
            $enCursoItem = $enCurso->get($cuota->id);
            $enCursoComprobante = $enCursoItem?->comprobante;
            $lineas[] = [
                'orden' => sprintf('%04d%02d', $i['anio'], $i['mes'] ?? 0),
                'cuota_id' => (int) $cuota->id,
                'cuota' => (string) $cuota->nombre,
                'periodo' => (string) $i['periodo'],
                'vencida' => $i['estado'] === 'vencida',
                'monto' => (float) $i['saldo'],
                'beca' => isset($i['beca']['etiqueta']) ? (string) $i['beca']['etiqueta'] : null,
                'bloque_id' => $bloque?->id,
                'bloque' => $bloque?->nombre,
                'sede' => $sede?->nombre,
                'profesor' => $bloque?->nombreTitular(),
                'abono_profesor' => $sede ? $sede->montoAbonoDocenteDesdeCuota((float) $cuota->monto) : 0.0,
                'en_curso' => $enCursoComprobante instanceof ComprobanteCuotaAlumno ? $enCursoComprobante->referencia : null,
            ];
        }
        usort($lineas, fn (array $a, array $b) => [$a['orden'], $a['bloque']] <=> [$b['orden'], $b['bloque']]);

        return collect($lineas);
    }

    /**
     * Por qué una cuota no aparece para pagar: cuántas hay pagas, becadas, con pago parcial
     * o con un pago en curso, en el año actual y el anterior.
     *
     * @return array{emitidas: int, pagadas: int, becadas: int, parciales: int, en_curso: int, anios: string}
     */
    public function resumen(Alumno $alumno): array
    {
        $anios = [now()->year - 1, now()->year];
        $items = collect($anios)->flatMap(fn (int $anio) => $this->estadoCuenta->paraAlumno($alumno, $anio)['items']);

        return [
            'emitidas' => $items->count(),
            'pagadas' => $items->where('estado', 'pagada')->count(),
            'becadas' => $items->where('estado', 'becada')->count(),
            'parciales' => $items->where('estado', 'parcial')->count(),
            'en_curso' => $this->pendientes($alumno)->whereNotNull('en_curso')->count(),
            'anios' => implode(' y ', $anios),
        ];
    }

    /** Arranca un pago: comprobante "esperando pago" con las cuotas elegidas. */
    public function iniciar(User $por, Alumno $alumno, array $cuotaIds, string $medio): ComprobanteCuotaAlumno
    {
        if (! in_array($medio, self::MEDIOS, true)) {
            throw ValidationException::withMessages(['medio' => 'Elegí cómo vas a pagar.']);
        }
        if ($medio === 'mercadopago' && ! $this->mercadoPagoDisponible()) {
            throw ValidationException::withMessages(['medio' => 'El pago con Mercado Pago no está disponible.']);
        }
        if ($medio === 'transferencia' && ! $this->datosTransferencia()) {
            throw ValidationException::withMessages(['medio' => 'Todavía no hay una cuenta cargada para transferir.']);
        }
        $elegidas = array_values(array_unique(array_map('intval', $cuotaIds)));
        $pendientes = $this->pendientes($alumno)->keyBy('cuota_id');
        $lineas = [];
        foreach ($elegidas as $id) {
            $linea = $pendientes->get($id);
            if (! $linea) {
                throw ValidationException::withMessages(['cuota_ids' => 'Una de las cuotas ya está paga o no te corresponde. Volvé a cargar la página.']);
            }
            if ($linea['en_curso']) {
                throw ValidationException::withMessages(['cuota_ids' => "La cuota {$linea['cuota']} ya tiene un pago en curso ({$linea['en_curso']}). Seguilo desde ahí o cancelalo."]);
            }
            $lineas[] = $linea;
        }
        if ($lineas === []) {
            throw ValidationException::withMessages(['cuota_ids' => 'Marcá al menos una cuota.']);
        }

        return DB::transaction(function () use ($por, $alumno, $lineas, $medio) {
            $comprobante = ComprobanteCuotaAlumno::create([
                'alumno_id' => $alumno->id,
                'sede_id' => app(ComprobanteService::class)->sedeDelAlumno($alumno) ?: null,
                'fecha_pago' => now()->toDateString(),
                'monto_total' => round(array_sum(array_column($lineas, 'monto')), 2),
                'estado' => 'esperando_pago',
                'origen' => $medio,
                'referencia' => $this->nuevaReferencia(),
                'notas' => $medio === 'mercadopago' ? 'Pago iniciado con Mercado Pago.' : 'Pago iniciado por transferencia.',
                'cargado_por_user_id' => $por->id,
            ]);
            foreach ($lineas as $linea) {
                ComprobanteCuotaAlumnoItem::create([
                    'comprobante_cuota_alumno_id' => $comprobante->id,
                    'cuota_id' => $linea['cuota_id'],
                    'bloque_id' => $linea['bloque_id'],
                    'monto' => $linea['monto'],
                ]);
            }

            return $comprobante;
        });
    }

    /** Lleva a Mercado Pago: crea la preferencia y devuelve la URL de pago. */
    public function linkMercadoPago(ComprobanteCuotaAlumno $c, ?string $email = null): string
    {
        $c->loadMissing(['items.cuota', 'items.bloque', 'alumno']);
        $items = [];
        foreach ($c->items as $it) {
            if (! $it instanceof ComprobanteCuotaAlumnoItem) {
                continue;
            }
            $cuota = $it->cuota;
            $bloque = $it->bloque;
            $alumno = $c->alumno;
            $partes = array_filter([
                $cuota instanceof Cuota ? $cuota->nombre : 'Cuota',
                $bloque instanceof Bloque ? $bloque->nombre : null,
                $alumno instanceof Alumno ? $alumno->nombre_apellido : null,
            ]);
            $items[] = ['title' => implode(' · ', $partes), 'unit_price' => (float) $it->monto];
        }
        $preferencia = $this->mercadoPago->crearPreferencia(
            (string) $c->referencia,
            $items,
            route('alumno.cuota.pago', $c->referencia),
            route('webhooks.mercadopago'),
            $email,
        );
        $c->update(['mp_preference_id' => $preferencia['id']]);

        return $preferencia['init_point'];
    }

    /** El alumno ya transfirió: adjunta el comprobante y queda para revisar. */
    public function adjuntarComprobante(ComprobanteCuotaAlumno $c, UploadedFile $archivo, string $fechaPago): ComprobanteCuotaAlumno
    {
        if (! $c->esperaPago()) {
            throw ValidationException::withMessages(['comprobante' => 'Este pago ya no está esperando comprobante.']);
        }
        $ext = strtolower((string) ($archivo->getClientOriginalExtension() ?: $archivo->guessExtension()));
        $ext = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? $ext : 'pdf';
        $path = $archivo->storeAs('comprobantes_cuota_alumnos', (string) Str::uuid().'.'.$ext, 'comprobantes');
        $c->update(['comprobante_path' => $path, 'fecha_pago' => $fechaPago, 'estado' => 'pendiente']);

        return $c;
    }

    /** El alumno se arrepintió antes de pagar. */
    public function cancelar(ComprobanteCuotaAlumno $c): void
    {
        if (! $c->esperaPago() || $c->pago_id) {
            throw ValidationException::withMessages(['pago' => 'Este pago ya no se puede cancelar.']);
        }
        DB::transaction(function () use ($c) {
            $c->items()->delete();
            $c->delete();
        });
    }

    /**
     * Aviso de Mercado Pago. Lee el pago desde la API y, si está aprobado y cubre el total,
     * registra el cobro. Se puede llamar varias veces con el mismo pago: registra una sola.
     */
    public function procesarAvisoMercadoPago(string $paymentId): ?ComprobanteCuotaAlumno
    {
        $pago = $this->mercadoPago->pago($paymentId);
        if (! $pago || empty($pago['external_reference'])) {
            return null;
        }

        return DB::transaction(function () use ($pago, $paymentId) {
            $c = ComprobanteCuotaAlumno::query()->where('referencia', $pago['external_reference'])->lockForUpdate()->first();
            if (! $c || $c->pago_id) {
                return $c;
            }
            if (($pago['status'] ?? null) !== 'approved') {
                return $c;
            }
            $cubre = ($pago['currency_id'] ?? 'ARS') === 'ARS' && (float) ($pago['transaction_amount'] ?? 0) + 0.01 >= (float) $c->monto_total;
            $c->update([
                'mp_payment_id' => $paymentId,
                'fecha_pago' => isset($pago['date_approved']) ? substr((string) $pago['date_approved'], 0, 10) : now()->toDateString(),
                'notas' => trim(($c->notas ?? '')."\nMercado Pago: pago {$paymentId} aprobado por $ ".number_format((float) ($pago['transaction_amount'] ?? 0), 2, ',', '.').'.'),
                'estado' => 'pendiente',
            ]);
            if (! $cubre) {
                // Queda para revisión manual: el importe no coincide con lo que se pidió.
                Log::warning('Pago de Mercado Pago con importe distinto al pedido', ['referencia' => $c->referencia, 'payment_id' => $paymentId]);

                return $c;
            }
            try {
                $this->pagos->aprobar($c, null);
            } catch (\RuntimeException $e) {
                // Por ejemplo, alguna cuota ya se había cobrado por otro lado: queda para revisar a mano.
                Log::warning('No se pudo registrar solo el pago de Mercado Pago', ['referencia' => $c->referencia, 'motivo' => $e->getMessage()]);
            }

            return $c->fresh();
        });
    }

    private function bloqueDeCuota(Cuota $cuota, Collection $bloques): ?Bloque
    {
        return match ($cuota->alcanceNormalizado()) {
            Cuota::ALCANCE_BLOQUE => $bloques->get($cuota->bloque_id) ?? $cuota->bloque,
            Cuota::ALCANCE_SEDE => $bloques->first(fn (Bloque $b) => (int) $b->sede_id === (int) $cuota->sede_id),
            default => $bloques->first(),
        };
    }

    private function nuevaReferencia(): string
    {
        do {
            // Sin letras que se confundan (0/O, 1/I): se dicta y se escribe en el concepto.
            $letras = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $ref = 'CH-'.implode('', array_map(fn () => $letras[random_int(0, strlen($letras) - 1)], range(1, 6)));
        } while (ComprobanteCuotaAlumno::query()->where('referencia', $ref)->exists());

        return $ref;
    }
}
