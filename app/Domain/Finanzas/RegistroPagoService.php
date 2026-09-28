<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\User;
use App\Services\AmbitoSedeService;
use App\Support\LiquidacionDocente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registro y edición de pagos de cuotas con liquidación docente (web y API).
 *
 * Un pago tiene líneas (alumno + cuota + monto). Reglas: la suma de las líneas es el
 * total, no hay dos líneas del mismo alumno y cuota, un alumno no paga dos veces la
 * misma cuota, la cuota tiene que aplicarle y quien registra tiene que tener alcance
 * sobre cada alumno. El abono al docente sale de la regla de la sede o de un total
 * manual repartido en proporción a cada línea.
 */
class RegistroPagoService
{
    /** @return array<string, mixed> */
    public function reglas(bool $edicion): array
    {
        $rules = [
            'fecha_pago' => 'required|date',
            'lineas' => 'required|array|min:1',
            'lineas.*.alumno_id' => 'required|exists:alumnos,id',
            'lineas.*.cuota_id' => 'required|exists:cuotas,id',
            'lineas.*.monto' => 'required|numeric|min:0.01',
            'monto_total' => 'required|numeric|min:0.01',
            'monto_abono_profesor' => 'nullable|numeric|min:0',
            'liquidar_profesor' => 'nullable|in:0,1',
            'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'notas' => 'nullable|string|max:1000',
        ];
        if ($edicion) {
            $rules['quitar_comprobante'] = 'nullable|boolean';
        }

        return $rules;
    }

    public function tablasDisponibles(): bool
    {
        return Schema::hasTable('pagos') && Schema::hasTable('pago_detalles') && Schema::hasTable('alumnos') && Schema::hasTable('cuotas');
    }

    /**
     * @param  array<string, mixed>  $validado  resultado de reglas()
     */
    public function registrar(array $validado, User $por, ?UploadedFile $comprobante = null): Pago
    {
        $path = $comprobante ? $this->guardarArchivo($comprobante) : null;

        try {
            return DB::transaction(function () use ($validado, $por, $path) {
                $plan = $this->preparar($validado, $por, null);
                $pago = Pago::create([
                    'fecha_pago' => $validado['fecha_pago'],
                    'monto_total' => $validado['monto_total'],
                    'comprobante_path' => $path,
                    'notas' => $validado['notas'] ?? null,
                    'registrado_por' => $por->id,
                ]);
                $this->crearDetalles($pago, $plan);

                return $pago;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('comprobantes')->delete($path);
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validado
     */
    public function actualizar(Pago $pago, array $validado, User $por, ?UploadedFile $comprobante = null, bool $quitarComprobante = false): Pago
    {
        $anterior = $pago->comprobante_path;
        $nuevo = $comprobante ? $this->guardarArchivo($comprobante) : null;

        try {
            DB::transaction(function () use ($pago, $validado, $por, $anterior, $nuevo, $quitarComprobante) {
                $plan = $this->preparar($validado, $por, $pago->id);
                $pago->update([
                    'fecha_pago' => $validado['fecha_pago'],
                    'monto_total' => $validado['monto_total'],
                    'comprobante_path' => $nuevo ?? ($quitarComprobante ? null : $anterior),
                    'notas' => $validado['notas'] ?? null,
                ]);
                $pago->detalles()->delete();
                $this->crearDetalles($pago, $plan);
            });
        } catch (\Throwable $e) {
            if ($nuevo) {
                Storage::disk('comprobantes')->delete($nuevo);
            }
            throw $e;
        }

        // El archivo reemplazado o quitado se borra recién cuando el cambio quedó guardado.
        if ($anterior && ($nuevo || $quitarComprobante)) {
            Storage::disk('comprobantes')->delete($anterior);
        }

        return $pago->fresh();
    }

    /**
     * Valida las reglas de negocio y calcula la liquidación. Se ejecuta dentro de la
     * transacción con las cuotas bloqueadas: dos registros simultáneos del mismo alumno
     * y cuota no pueden pasar ambos el control de duplicados.
     *
     * @param  array<string, mixed>  $validado
     * @return array{lineas: array<int, array<string, mixed>>, cuotas: Collection<int, Cuota>, liquidar: bool, totalAbono: float|null, abonosPreset: array<int, float>, usarPreset: bool}
     */
    private function preparar(array $validado, User $por, ?int $exceptoPagoId): array
    {
        $liquidar = (string) ($validado['liquidar_profesor'] ?? '1') === '1';
        $lineas = array_values($validado['lineas']);

        $vistos = [];
        foreach ($lineas as $linea) {
            $par = $linea['alumno_id'].'-'.$linea['cuota_id'];
            if (isset($vistos[$par])) {
                throw ValidationException::withMessages([
                    'lineas' => 'Hay líneas duplicadas (mismo alumno y misma cuota). Unificá el monto en una sola línea o separá en otro pago.',
                ]);
            }
            $vistos[$par] = true;
        }

        $suma = round(array_sum(array_map(fn ($l) => (float) $l['monto'], $lineas)), 2);
        $total = round((float) $validado['monto_total'], 2);
        if (abs($suma - $total) > 0.02) {
            throw ValidationException::withMessages([
                'monto_total' => 'El monto total ($'.number_format($total, 2, ',', '.').') debe coincidir con la suma de las líneas ($'.number_format($suma, 2, ',', '.').').',
            ]);
        }

        $cuotaIds = array_values(array_unique(array_map(fn ($l) => (int) $l['cuota_id'], $lineas)));
        $cuotas = Cuota::query()->with(['bloque.sede', 'sede'])->whereIn('id', $cuotaIds)->lockForUpdate()->get()->keyBy('id');
        $permiso = $exceptoPagoId !== null ? 'pagos.update' : 'pagos.create';

        foreach ($lineas as $idx => $linea) {
            $cuota = $cuotas->get((int) $linea['cuota_id']);
            if (! $cuota) {
                throw ValidationException::withMessages(['lineas.'.$idx.'.cuota_id' => 'Cuota no válida.']);
            }
            $alumnoId = (int) $linea['alumno_id'];
            $duplicado = PagoDetalle::query()->where('cuota_id', $cuota->id)->where('alumno_id', $alumnoId)
                ->when($exceptoPagoId !== null, fn ($q) => $q->where('pago_id', '!=', $exceptoPagoId));
            if ($duplicado->exists()) {
                throw ValidationException::withMessages([
                    'lineas.'.$idx.'.alumno_id' => 'Este alumno ya tiene pago registrado para la cuota elegida en esa línea (en otro pago).',
                ]);
            }
            $alumno = Alumno::query()->find($alumnoId);
            if ($alumno && ! $por->acceso()->puedeSobreAlumno($permiso, $alumno)) {
                abort(403, 'No podés registrar pagos de alumnos fuera de tu alcance.');
            }
            if (! $alumno || ! $cuota->aplicaAAlumno($alumno)) {
                throw ValidationException::withMessages([
                    'lineas.'.$idx.'.alumno_id' => 'El alumno de la línea '.($idx + 1).' no corresponde a la cuota según alcance / bloques.',
                ]);
            }
        }

        $abonosPreset = $liquidar ? LiquidacionDocente::abonosPorLinea($lineas, $cuotas) : [];
        $manual = $validado['monto_abono_profesor'] ?? null;
        $usarPreset = $liquidar && ($manual === null || $manual === '');
        $totalAbono = $liquidar ? ($usarPreset ? round(array_sum($abonosPreset), 2) : max(0.0, round((float) $manual, 2))) : null;

        return ['lineas' => $lineas, 'cuotas' => $cuotas, 'liquidar' => $liquidar, 'totalAbono' => $totalAbono, 'abonosPreset' => $abonosPreset, 'usarPreset' => $usarPreset];
    }

    /**
     * @param  array{lineas: array<int, array<string, mixed>>, cuotas: Collection<int, Cuota>, liquidar: bool, totalAbono: float|null, abonosPreset: array<int, float>, usarPreset: bool}  $plan
     */
    private function crearDetalles(Pago $pago, array $plan): void
    {
        $lineas = $plan['lineas'];
        $conAbono = $plan['liquidar'] && Schema::hasColumn('pago_detalles', 'abono_profesor');
        $abonos = [];
        if ($conAbono && $plan['totalAbono'] !== null && $lineas !== []) {
            $abonos = $plan['usarPreset'] && $plan['abonosPreset'] !== []
                ? $plan['abonosPreset']
                : $this->repartirProporcional(array_map(fn ($l) => (float) $l['monto'], $lineas), $plan['totalAbono']);
        }

        foreach ($lineas as $idx => $linea) {
            /** @var Cuota $cuota */
            $cuota = $plan['cuotas']->get((int) $linea['cuota_id']);
            $alumnoId = (int) $linea['alumno_id'];
            $cuotaRef = (float) $cuota->monto;
            $sede = LiquidacionDocente::sedeParaCuota($cuota);
            $sedeNombre = $sede?->nombre ?? $cuota->bloque?->sede?->nombre ?? $cuota->sede?->nombre
                ?? Alumno::query()->with('sede')->find($alumnoId)?->sede?->nombre ?? '—';

            $det = ['pago_id' => $pago->id, 'alumno_id' => $alumnoId, 'cuota_id' => (int) $linea['cuota_id'], 'monto' => round((float) $linea['monto'], 2)];
            if ($conAbono) {
                $abono = (float) ($abonos[$idx] ?? 0.0);
                $det['abono_profesor'] = $abono;
                $det['abono_base'] = $cuotaRef;
                $det['abono_porcentaje'] = $cuotaRef > 0 ? round(100 * $abono / $cuotaRef, 4) : null;
                $det['abono_nota'] = $plan['usarPreset'] && $sede
                    ? sprintf('Abono según regla sede %s: %s. Cuota ref. $%s.', $sedeNombre, $sede->resumenLiquidacionDocente($cuotaRef), number_format($cuotaRef, 2, ',', '.'))
                    : sprintf(
                        'Abono docente $%s (total manual $%s, reparto proporcional por línea). Cuota ref. $%s. Sede: %s.',
                        number_format($abono, 2, ',', '.'),
                        number_format((float) ($plan['totalAbono'] ?? 0), 2, ',', '.'),
                        number_format($cuotaRef, 2, ',', '.'),
                        $sedeNombre
                    );
                if (strlen((string) $det['abono_nota']) > 500) {
                    $det['abono_nota'] = substr((string) $det['abono_nota'], 0, 497).'...';
                }
            }
            PagoDetalle::create($det);
        }
    }

    /**
     * Reparte el abono total al docente entre líneas en proporción al monto (centavos exactos).
     *
     * @param  array<int, float>  $montos
     * @return array<int, float>
     */
    public function repartirProporcional(array $montos, float $total): array
    {
        $n = count($montos);
        $centTot = (int) round($total * 100);
        $suma = array_sum($montos);
        if ($n === 0) {
            return [];
        }
        if ($centTot <= 0 || $suma <= 0) {
            return array_fill(0, $n, 0.0);
        }
        $out = [];
        $asignados = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $cent = $centTot - $asignados;
            } else {
                $cent = (int) floor(($centTot * $montos[$i] / $suma) + 1e-9);
                $asignados += $cent;
            }
            $out[$i] = $cent / 100.0;
        }

        return $out;
    }

    /**
     * Cuotas que el usuario puede cobrar, con la liquidación docente de referencia.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cuotasParaCobrar(User $user): Collection
    {
        $ambito = app(AmbitoSedeService::class);
        $filtro = $ambito->idsPara($user, 'pagos.view');
        $q = Cuota::query();
        if ($filtro !== null) {
            $ambito->aplicarCuotas($q, $filtro);
        }
        if (Schema::hasColumn('cuotas', 'activo')) {
            $q->orderBy('activo', 'desc');
        }
        $cuotas = $q->with(['bloque.sede', 'bloque.profesor', 'sede'])->orderBy('año', 'desc')->orderBy('mes', 'desc')->orderBy('id', 'desc')->get();

        return $cuotas->map(function (Cuota $c) {
            $sede = LiquidacionDocente::sedeParaCuota($c);

            return [
                'id' => $c->id,
                'monto' => (float) $c->monto,
                'label' => ($c->nombre_mes ? $c->nombre_mes.' '.$c->año.' — ' : '').$c->nombre.' — $ '.number_format((float) $c->monto, 2, ',', '.'),
                'nombre' => $c->nombre,
                'anio' => (int) $c->año,
                'mes' => $c->mes,
                'alcance' => Schema::hasColumn('cuotas', 'alcance') ? ($c->alcance ?? 'bloque') : 'bloque',
                'bloque_id' => $c->bloque_id,
                'bloque' => $c->bloque?->nombre,
                'sede_id' => $c->sede_id,
                'sede_nombre' => $sede?->nombre,
                'activo' => (bool) ($c->activo ?? true),
                'abono_docente_ref' => $sede ? $sede->montoAbonoDocenteDesdeCuota((float) $c->monto) : 0.0,
                'liquidacion_resumen' => $sede ? $sede->resumenLiquidacionDocente((float) $c->monto) : null,
            ];
        })->values();
    }

    /**
     * Alumnos que pueden sumarse a un pago de esta cuota: le aplica la cuota (bloque, sede
     * o general; lista de alumnos si la tiene) y todavía no la pagaron.
     *
     * @return list<array{id: int, nombre_apellido: string, sede_nombre: ?string, bloque_nombre: string}>
     */
    public function alumnosParaCuota(Cuota $cuota, ?int $exceptoPagoId = null): array
    {
        if (! Schema::hasTable('pago_detalles') || ! Schema::hasTable('alumnos')) {
            return [];
        }
        $cuota->loadMissing(['bloque.sede', 'sede']);
        $query = Alumno::query()->where('activo', true)->orderBy('nombre_apellido')->with('sede');
        $alcance = Schema::hasColumn('cuotas', 'alcance') ? $cuota->alcanceNormalizado() : Cuota::ALCANCE_BLOQUE;

        if ($alcance === Cuota::ALCANCE_BLOQUE) {
            if (! $cuota->bloque_id) {
                return [];
            }
            $bid = (int) $cuota->bloque_id;
            if (Schema::hasTable('alumno_bloque')) {
                $query->where(fn ($q) => $q->whereHas('bloques', fn ($sub) => $sub->where('bloques.id', $bid))->orWhere('bloque_id', $bid));
            } else {
                $query->where('bloque_id', $bid);
            }
            $ctx = $cuota->bloque?->nombre ?? '';
        } elseif ($alcance === Cuota::ALCANCE_GENERAL) {
            $query->where(fn ($q) => $q->whereHas('bloques')->orWhereNotNull('bloque_id'));
            $ctx = 'Cuota general';
        } elseif ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
            $sid = (int) $cuota->sede_id;
            $query->where(fn ($q) => $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))->orWhere('sede_id', $sid));
            $ctx = $cuota->sede?->nombre ?? 'Sede';
        } else {
            return [];
        }

        if (Schema::hasTable('cuota_alumno')) {
            $soloCuota = $cuota->alumnos()->pluck('alumnos.id');
            if ($soloCuota->isNotEmpty()) {
                $query->whereIn('alumnos.id', $soloCuota->all());
            }
        }
        $yaPagaron = PagoDetalle::query()->where('cuota_id', $cuota->id)
            ->when($exceptoPagoId !== null, fn ($q) => $q->where('pago_id', '!=', $exceptoPagoId))
            ->pluck('alumno_id')->unique()->filter()->values();
        if ($yaPagaron->isNotEmpty()) {
            $query->whereNotIn('alumnos.id', $yaPagaron->all());
        }

        return $query->get(['alumnos.id', 'alumnos.nombre_apellido', 'alumnos.sede_id'])->map(fn (Alumno $a) => [
            'id' => $a->id,
            'nombre_apellido' => $a->nombre_apellido,
            'sede_nombre' => $a->sede?->nombre,
            'bloque_nombre' => $ctx,
        ])->values()->all();
    }

    private function guardarArchivo(UploadedFile $upload): string
    {
        $ext = strtolower((string) $upload->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = strtolower((string) ($upload->guessExtension() ?: 'pdf'));
        }
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }

        return $upload->storeAs('pagos', (string) Str::uuid().'.'.$ext, 'comprobantes');
    }
}
