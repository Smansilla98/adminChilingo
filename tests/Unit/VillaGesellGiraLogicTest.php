<?php

namespace Tests\Unit;

use App\Support\VillaGesellCalculo;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class VillaGesellGiraLogicTest extends TestCase
{
    public function test_segunda_quincena_enero_y_primera_febrero_son_30_dias(): void
    {
        $inicio = Carbon::parse('2027-01-16');
        $fin = Carbon::parse('2027-02-14');

        $this->assertSame(30, VillaGesellCalculo::cantidadDias($inicio, $fin));
        $rango = VillaGesellCalculo::rangoFechas($inicio, $fin);
        $this->assertCount(30, $rango);
        $this->assertSame('2027-01-16', $rango[0]->toDateString());
        $this->assertSame('2027-02-14', $rango[29]->toDateString());
    }

    public function test_gasto_diario_se_proyecta_por_todos_los_dias(): void
    {
        $this->assertSame(300000.0, VillaGesellCalculo::proyectarGasto(10000, 'diario', 'por_dia', 30));
    }

    public function test_gasto_fijo_no_se_multiplica(): void
    {
        $this->assertSame(50000.0, VillaGesellCalculo::proyectarGasto(50000, 'fijo', 'total', 30));
    }

    public function test_valor_por_dia_por_cantidad_de_dias(): void
    {
        $this->assertSame(150000.0, VillaGesellCalculo::porDiaPorDias(5000, 30));
        $this->assertSame(0.0, VillaGesellCalculo::porDiaPorDias(5000, 0));
    }
}
