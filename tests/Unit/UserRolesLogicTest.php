<?php

namespace Tests\Unit;

use App\Models\Asistencia;
use PHPUnit\Framework\TestCase;

/**
 * La lógica de roles ahora depende de datos (sedes, bloques, asignaciones):
 * sus tests están en Tests\Feature\AccesoParidadLegacyTest.
 */
class UserRolesLogicTest extends TestCase
{
    public function test_asistencia_presente_helper(): void
    {
        $this->assertTrue(Asistencia::esPresente('presente'));
        $this->assertTrue(Asistencia::esPresente('tarde'));
        $this->assertFalse(Asistencia::esPresente('ausencia_injustificada'));
        $this->assertFalse(Asistencia::esPresente('feriado'));
    }
}
