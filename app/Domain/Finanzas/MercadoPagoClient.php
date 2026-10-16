<?php

namespace App\Domain\Finanzas;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API de Mercado Pago (Checkout Pro).
 * https://www.mercadopago.com.ar/developers/es/reference
 */
class MercadoPagoClient
{
    private const API = 'https://api.mercadopago.com';

    public function configurado(): bool
    {
        return filled(config('services.mercadopago.access_token'));
    }

    /**
     * Crea la preferencia de pago y devuelve ['id' => ..., 'init_point' => ...].
     *
     * @param  array<int, array{title: string, unit_price: float}>  $items
     */
    public function crearPreferencia(string $referencia, array $items, string $volverA, string $avisarA, ?string $email = null): array
    {
        $respuesta = $this->http()->post(self::API.'/checkout/preferences', array_filter([
            'external_reference' => $referencia,
            'items' => array_map(fn (array $item) => [
                'title' => mb_substr($item['title'], 0, 250),
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => round($item['unit_price'], 2),
            ], $items),
            'payer' => $email ? ['email' => $email] : null,
            'back_urls' => ['success' => $volverA, 'pending' => $volverA, 'failure' => $volverA],
            'auto_return' => 'approved',
            'notification_url' => $avisarA,
            'statement_descriptor' => 'LA CHILINGA',
        ]));
        if (! $respuesta->successful() || ! $respuesta->json('init_point')) {
            report(new RuntimeException('Mercado Pago rechazó la preferencia: '.$respuesta->status().' '.$respuesta->body()));
            throw new RuntimeException('No pudimos abrir Mercado Pago. Probá de nuevo en un rato o pagá por transferencia.');
        }

        return ['id' => (string) $respuesta->json('id'), 'init_point' => (string) $respuesta->json('init_point')];
    }

    /** Lee un pago desde la API: es la única fuente confiable (el aviso no se cree a ciegas). */
    public function pago(string $id): ?array
    {
        $respuesta = $this->http()->get(self::API.'/v1/payments/'.rawurlencode($id));

        return $respuesta->successful() ? $respuesta->json() : null;
    }

    /**
     * Verifica la firma del aviso (cabecera x-signature) si hay clave configurada.
     * Formato de Mercado Pago: "ts=...,v1=..." firmado sobre "id:{data.id};request-id:{x-request-id};ts:{ts};".
     */
    public function firmaValida(?string $firma, ?string $requestId, string $dataId): bool
    {
        $secreto = config('services.mercadopago.webhook_secret');
        if (blank($secreto)) {
            return true;
        }
        $partes = [];
        foreach (explode(',', (string) $firma) as $par) {
            [$clave, $valor] = array_pad(explode('=', trim($par), 2), 2, null);
            $partes[$clave] = $valor;
        }
        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }
        $manifiesto = 'id:'.strtolower($dataId).';'.($requestId ? 'request-id:'.$requestId.';' : '').'ts:'.$partes['ts'].';';

        return hash_equals(hash_hmac('sha256', $manifiesto, (string) $secreto), (string) $partes['v1']);
    }

    private function http()
    {
        return Http::withToken((string) config('services.mercadopago.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300, throw: false);
    }
}
