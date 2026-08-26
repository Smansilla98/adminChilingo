<?php

namespace Tests\Unit;

use App\Models\Cuota;
use App\Models\User;
use App\Services\AmbitoSedeService;
use PHPUnit\Framework\TestCase;

class AmbitoSedeServiceTest extends TestCase
{
    public function test_ids_para_admin_es_null(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['acotaPorSede'])
            ->getMock();
        $user->method('acotaPorSede')->willReturn(false);

        $svc = new AmbitoSedeService;
        $this->assertNull($svc->idsPara($user));
        $this->assertSame('Vista general de la escuela', $svc->etiqueta($user));
    }

    public function test_ids_para_coordinador_usa_sedes_operativas(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['acotaPorSede', 'sedeIdsOperativas'])
            ->getMock();
        $user->method('acotaPorSede')->willReturn(true);
        $user->method('sedeIdsOperativas')->willReturn([3, 7]);

        $svc = new AmbitoSedeService;
        $this->assertSame([3, 7], $svc->idsPara($user));
        $this->assertSame('Indicadores de tus sedes', $svc->etiqueta($user));
    }

    public function test_ids_para_coordinador_sin_sedes_usa_cero(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['acotaPorSede', 'sedeIdsOperativas'])
            ->getMock();
        $user->method('acotaPorSede')->willReturn(true);
        $user->method('sedeIdsOperativas')->willReturn([]);

        $svc = new AmbitoSedeService;
        $this->assertSame([0], $svc->idsPara($user));
    }

    public function test_cuota_toca_sedes_por_alcance(): void
    {
        $svc = new AmbitoSedeService;

        $general = new Cuota;
        $general->alcance = Cuota::ALCANCE_GENERAL;
        $this->assertTrue($svc->cuotaTocaSedes($general, [1]));

        $sedeOk = new Cuota;
        $sedeOk->alcance = Cuota::ALCANCE_SEDE;
        $sedeOk->sede_id = 2;
        $this->assertTrue($svc->cuotaTocaSedes($sedeOk, [2, 5]));
        $this->assertFalse($svc->cuotaTocaSedes($sedeOk, [1]));
    }
}
