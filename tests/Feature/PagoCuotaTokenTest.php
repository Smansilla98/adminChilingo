<?php

namespace Tests\Feature;

use App\Support\PagoCuotaToken;
use Carbon\Carbon;
use Tests\TestCase;

class PagoCuotaTokenTest extends TestCase
{
    public function test_emite_y_lee_alumno_y_sede(): void
    {
        $token = PagoCuotaToken::emitir(42, 7, 20);
        $payload = PagoCuotaToken::leer($token);
        $this->assertSame(42, $payload['alumno_id']);
        $this->assertSame(7, $payload['sede_id']);
    }

    public function test_rechaza_token_vencido(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');
        $token = PagoCuotaToken::emitir(1, 1, 1);
        Carbon::setTestNow('2026-08-19 12:02:00');
        $this->assertNull(PagoCuotaToken::leer($token));
        Carbon::setTestNow();
    }

    public function test_rechaza_basura(): void
    {
        $this->assertNull(PagoCuotaToken::leer('no-es-un-token'));
        $this->assertNull(PagoCuotaToken::leer(''));
        $this->assertNull(PagoCuotaToken::leer(null));
    }

    public function test_salud_responde_json(): void
    {
        $this->getJson(route('salud'))
            ->assertJsonStructure(['ok', 'app', 'time']);
    }
}
