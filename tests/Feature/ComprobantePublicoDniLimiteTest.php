<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La consulta pública por DNI tiene límite por DNI, además del límite por IP. */
class ComprobantePublicoDniLimiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mismo_dni_se_bloquea_aunque_llegue_desde_otras_ips(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->getJson('/pagar-cuota/api/alumnos?dni=30123456');
            $this->assertNotSame(429, $respuesta->getStatusCode(), "la consulta {$i} ya no debería estar bloqueada");
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->getJson('/pagar-cuota/api/alumnos?dni=30123456')
            ->assertStatus(429);
    }
}
