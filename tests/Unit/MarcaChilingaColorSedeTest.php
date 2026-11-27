<?php

namespace Tests\Unit;

use App\Support\MarcaChilinga;
use PHPUnit\Framework\TestCase;

class MarcaChilingaColorSedeTest extends TestCase
{
    public function test_devuelve_el_color_de_marca_ignorando_tildes_y_mayusculas(): void
    {
        $this->assertSame('#2BA8E0', MarcaChilinga::colorSede('Sarandí'));
        $this->assertSame('#2BA8E0', MarcaChilinga::colorSede('SARANDI'));
        $this->assertSame('#B5452A', MarcaChilinga::colorSede('San Marcos Sierras'));
        $this->assertSame('#F26A1B', MarcaChilinga::colorSede('Palomar'));
    }

    public function test_devuelve_null_para_sedes_desconocidas_o_vacias(): void
    {
        $this->assertNull(MarcaChilinga::colorSede('Sede Nueva'));
        $this->assertNull(MarcaChilinga::colorSede(null));
        $this->assertNull(MarcaChilinga::colorSede(''));
    }
}
