<?php

namespace Tests\Unit;

use App\Domain\Acceso\Alcance;
use App\Domain\Acceso\PermisosEfectivos;
use App\Models\Cuota;
use App\Models\User;
use App\Services\AmbitoSedeService;
use PHPUnit\Framework\TestCase;

class AmbitoSedeServiceTest extends TestCase
{
    /** Usuario simulado con un único permiso y el alcance dado. */
    private function userCon(string $permiso, Alcance $alcance): User
    {
        $user = $this->getMockBuilder(User::class)->onlyMethods(['acceso'])->getMock();
        $user->method('acceso')->willReturn(new PermisosEfectivos(1, [], [$permiso => $alcance], [], false));

        return $user;
    }

    public function test_ids_para_alcance_global_es_null(): void
    {
        $svc = new AmbitoSedeService;
        $user = $this->userCon('alumnos.view', Alcance::total());

        $this->assertNull($svc->idsPara($user));
        $this->assertSame('Vista general de la escuela', $svc->etiqueta($user));
    }

    public function test_ids_para_alcance_de_sede_usa_esas_sedes(): void
    {
        $svc = new AmbitoSedeService;
        $user = $this->userCon('pagos.view', Alcance::vacio()->agregarSede(3)->agregarSede(7));

        $this->assertSame([3, 7], $svc->idsPara($user, 'pagos.view'));
        $this->assertSame('Indicadores de tus sedes', $svc->etiqueta($user, 'pagos.view'));
        // Otro permiso que no tiene: no ve nada.
        $this->assertSame([0], $svc->idsPara($user, 'gastos.view'));
    }

    public function test_ids_para_alcance_solo_de_bloque_no_habilita_listados_por_sede(): void
    {
        $svc = new AmbitoSedeService;
        $user = $this->userCon('alumnos.view', Alcance::vacio()->agregarBloque(10, 3));

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
