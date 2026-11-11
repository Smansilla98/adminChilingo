<?php

namespace App\Domain\Finanzas;

use App\Models\Pago;
use App\Models\SyncOperacion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registro de un pago de cuotas: única puerta para el panel web y la API.
 *
 * - Normaliza el abono docente (vacío = regla de la sede) y valida con RegistroPagoService.
 * - Idempotencia opcional: `client_uuid` (o el encabezado Idempotency-Key). Si llega dos veces
 *   la misma clave (doble clic, reintento de la app), devuelve el pago ya registrado en vez de
 *   duplicarlo. La marca se guarda en la misma transacción que el pago.
 */
class RegistrarPago
{
    public const TIPO = 'pago';

    public function __construct(private RegistroPagoService $registro) {}

    /**
     * @param  array<string, mixed>  $entrada  datos del formulario o del cuerpo JSON
     * @return array{pago: Pago, duplicado: bool}
     */
    public function ejecutar(array $entrada, User $por, ?UploadedFile $comprobante = null, ?string $idempotencia = null): array
    {
        $clave = $this->clave($entrada['client_uuid'] ?? null, $idempotencia);
        if ($clave && ($previo = $this->previo($clave, $por))) {
            return ['pago' => $previo, 'duplicado' => true];
        }

        if (($entrada['monto_abono_profesor'] ?? null) === '') {
            $entrada['monto_abono_profesor'] = null;
        }
        $datos = Validator::make($entrada + ['comprobante' => $comprobante], $this->registro->reglas(false))->validate();

        try {
            $pago = DB::transaction(function () use ($datos, $por, $comprobante, $clave) {
                $pago = $this->registro->registrar($datos, $por, $comprobante);
                if ($clave) {
                    SyncOperacion::query()->create(['client_uuid' => $clave, 'user_id' => $por->id, 'tipo' => self::TIPO, 'respuesta' => ['pago_id' => $pago->id]]);
                }

                return $pago;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Otro envío con la misma clave ganó la carrera: este se revirtió entero.
            $previo = $clave ? $this->previo($clave, $por) : null;
            if (! $previo) {
                throw $e;
            }

            return ['pago' => $previo, 'duplicado' => true];
        }

        return ['pago' => $pago, 'duplicado' => false];
    }

    /**
     * La clave tiene que ser un UUID; una Idempotency-Key con otro formato se convierte en un
     * UUID estable (siempre el mismo para el mismo texto).
     */
    private function clave(mixed $clientUuid, ?string $encabezado): ?string
    {
        $valor = trim((string) ($clientUuid ?: $encabezado));
        if ($valor === '') {
            return null;
        }
        if (Str::isUuid($valor)) {
            return strtolower($valor);
        }
        if (mb_strlen($valor) > 200) {
            throw ValidationException::withMessages(['client_uuid' => 'La clave de idempotencia es demasiado larga.']);
        }
        $h = sha1('pago:'.$valor);

        return sprintf('%s-%s-5%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), dechex((hexdec(substr($h, 16, 2)) & 0x3F) | 0x80).substr($h, 18, 2), substr($h, 20, 12));
    }

    private function previo(string $clave, User $por): ?Pago
    {
        $op = SyncOperacion::query()->where('client_uuid', $clave)->first();
        if (! $op) {
            return null;
        }
        if ($op->tipo !== self::TIPO || ($op->user_id !== null && (int) $op->user_id !== (int) $por->id)) {
            throw ValidationException::withMessages(['client_uuid' => 'Esa clave ya se usó para otra operación. Volvé a cargar el formulario.']);
        }

        return Pago::query()->find($op->respuesta['pago_id'] ?? 0);
    }
}
