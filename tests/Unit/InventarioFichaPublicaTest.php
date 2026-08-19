<?php

namespace Tests\Unit;

use App\Models\InventarioItem;
use PHPUnit\Framework\TestCase;

class InventarioFichaPublicaTest extends TestCase
{
    public function test_ficha_publica_no_incluye_alumno(): void
    {
        $item = new InventarioItem;
        $item->codigo = 'CHL-0042';
        $item->nombre = 'Repique 12"';
        $item->tipo = 'instrumento';
        $item->estado = 'bueno';
        $item->marca = 'Luthier';
        $item->medida = '12"';
        $item->setRelation('sede', null);
        $item->alumno_id = 99;

        $ficha = $item->fichaPublica();
        $this->assertSame('CHL-0042', $ficha['codigo']);
        $this->assertArrayNotHasKey('alumno', $ficha);
        $this->assertArrayNotHasKey('alumno_id', $ficha);
        $encoded = json_encode($ficha);
        $this->assertStringNotContainsString('99', $encoded);
    }
}
