<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Token de corta vida para el flujo público de cuota (evita enviar alumno_id suelto).
 */
final class PagoCuotaToken
{
    public static function emitir(int $alumnoId, int $sedeId, int $ttlMinutos = 20): string
    {
        return Crypt::encryptString(json_encode([
            'a' => $alumnoId,
            's' => $sedeId,
            'e' => now()->addMinutes($ttlMinutos)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{alumno_id: int, sede_id: int}|null
     */
    public static function leer(?string $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($data)) {
            return null;
        }
        $exp = (int) ($data['e'] ?? 0);
        if ($exp < now()->getTimestamp()) {
            return null;
        }

        return [
            'alumno_id' => (int) ($data['a'] ?? 0),
            'sede_id' => (int) ($data['s'] ?? 0),
        ];
    }
}
