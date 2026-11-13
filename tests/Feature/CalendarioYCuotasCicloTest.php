<?php

namespace Tests\Feature;

use App\Domain\Academico\CalendarioAcademico;
use App\Domain\Academico\CuotasDelCicloService;
use App\Domain\Personas\CicloLectivoService;
use App\Models\CicloLectivo;
use App\Models\CicloModulo;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Ciclo modular: cuotas marzo–febrero, calendario académico (enero taller, febrero gira) y generación sin tocar lo existente. */
class CalendarioYCuotasCicloTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function ciclo(int $anio = 2027): CicloLectivo
    {
        return app(CicloLectivoService::class)->abrir(['anio' => $anio], $this->admin());
    }

    public function test_el_periodo_de_cuotas_va_de_marzo_a_febrero(): void
    {
        $meses = CicloLectivo::periodoCuotas(2027);

        $this->assertCount(12, $meses);
        $this->assertSame(['anio' => 2027, 'mes' => 3], $meses[0]);
        $this->assertSame(['anio' => 2028, 'mes' => 2], $meses[11]);
        $this->assertSame(['anio' => 2027, 'mes' => 12], $meses[9]);
    }

    public function test_abrir_crea_los_modulos_con_sus_fechas(): void
    {
        $ciclo = $this->ciclo();

        $modulos = $ciclo->modulos()->get()->keyBy('clave');
        $this->assertSame('2027-03-01', $modulos[CicloModulo::CUOTAS]->inicio->toDateString());
        $this->assertSame('2028-02-28', $modulos[CicloModulo::CUOTAS]->fin->toDateString());
        $this->assertSame('2027-12-15', $modulos[CicloModulo::ACADEMICO]->fin->toDateString());
        $this->assertTrue($modulos->has(CicloModulo::REINSCRIPCION));
        $this->assertSame('2027-03-01', $ciclo->inicio->toDateString(), 'el ciclo guarda el periodo de cuotas');
    }

    public function test_enero_y_febrero_tienen_taller_de_verano_y_gira_en_paralelo(): void
    {
        $cal = CalendarioAcademico::para($this->ciclo());

        $porMes = collect($cal)->keyBy('mes');
        $tipos = fn (int $mes) => collect($porMes[$mes]['actividades'])->pluck('tipo')->all();
        $this->assertSame([CalendarioAcademico::TALLER_VERANO, CalendarioAcademico::GIRA_GESELL], $tipos(1));
        $this->assertSame([CalendarioAcademico::TALLER_VERANO, CalendarioAcademico::GIRA_GESELL], $tipos(2));
        $this->assertSame(['Taller de verano', 'Gira a Villa Gesell'], collect($porMes[1]['actividades'])->pluck('etiqueta')->all());
        $this->assertSame([CalendarioAcademico::CLASES], $tipos(3));
        $this->assertSame([CalendarioAcademico::CLASES], $tipos(12));
        $this->assertTrue(collect($cal)->every(fn ($m) => $m['cuotas'] === true), 'las cuotas siguen todos los meses');
        $this->assertSame('Enero 2028', $cal[10]['nombre']);
    }

    public function test_un_mes_sin_configuracion_es_solo_clases(): void
    {
        config(['chilinga.calendario_academico' => ['1' => ['taller_verano']]]);
        $porMes = collect(CalendarioAcademico::para($this->ciclo()))->keyBy('mes');

        $this->assertSame([['tipo' => 'taller_verano', 'etiqueta' => 'Taller de verano']], $porMes[1]['actividades']);
        $this->assertSame([['tipo' => 'clases', 'etiqueta' => 'Clases']], $porMes[2]['actividades']);
    }

    public function test_la_api_y_la_pantalla_muestran_el_calendario(): void
    {
        $ciclo = $this->ciclo();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/ciclos-lectivos/'.$ciclo->id.'/calendario')->assertOk()
            ->assertJsonCount(12, 'data')->assertJsonPath('data.0.actividades.0.tipo', 'clases')
            ->assertJsonPath('data.10.actividades.0.tipo', 'taller_verano')->assertJsonPath('data.10.actividades.1.tipo', 'gira_gesell');

        $this->actingAs($this->admin())->get(route('ciclos.show', $ciclo))->assertOk()
            ->assertSee('Taller de verano')->assertSee('Gira a Villa Gesell');
    }

    public function test_genera_las_cuotas_marzo_a_febrero_de_cada_bloque_activo(): void
    {
        $sede = $this->sede('Sede Cuotas');
        $b1 = $this->bloque($sede, 'A');
        $b2 = $this->bloque($sede, 'B');
        $inactivo = $this->bloque($sede, 'Cerrado');
        $inactivo->update(['activo' => false]);
        $ciclo = $this->ciclo();

        $r = app(CuotasDelCicloService::class)->generar($ciclo, 1500, [], $this->admin());

        $this->assertSame(['creadas' => 24, 'existentes' => 0], $r);
        $this->assertSame(0, Cuota::query()->where('bloque_id', $inactivo->id)->count());
        $primera = Cuota::query()->where('bloque_id', $b1->id)->where('año', 2027)->where('mes', 3)->sole();
        $this->assertSame(1500.0, (float) $primera->monto);
        $this->assertSame('Cuota 03/2027', $primera->nombre);
        $this->assertTrue(Cuota::query()->where('bloque_id', $b2->id)->where('año', 2028)->where('mes', 2)->exists());
        $this->assertFalse(Cuota::query()->where('bloque_id', $b1->id)->where('año', 2027)->where('mes', 2)->exists(), 'febrero del año anterior no entra');
    }

    public function test_volver_a_generar_no_duplica_ni_toca_las_cuotas_existentes(): void
    {
        $sede = $this->sede('Sede Existente');
        $bloque = $this->bloque($sede, 'C');
        $ciclo = $this->ciclo();
        // Cuota ya cargada a mano para marzo, con otro monto y un pago en marcha.
        $existente = Cuota::query()->create([
            'alcance' => Cuota::ALCANCE_BLOQUE, 'bloque_id' => $bloque->id, 'nombre' => 'Marzo especial',
            'año' => 2027, 'mes' => 3, 'monto' => 5000, 'activo' => true,
        ]);

        $servicio = app(CuotasDelCicloService::class);
        $primera = $servicio->generar($ciclo, 1000, [], $this->admin());
        $segunda = $servicio->generar($ciclo, 1000, [], $this->admin());

        $this->assertSame(11, $primera['creadas'], 'marzo ya existía');
        $this->assertSame(0, $segunda['creadas']);
        $this->assertSame(12, $segunda['existentes']);
        $existente->refresh();
        $this->assertSame('Marzo especial', $existente->nombre);
        $this->assertSame(5000.0, (float) $existente->monto);
        $this->assertSame(12, Cuota::query()->where('bloque_id', $bloque->id)->count());
    }

    public function test_monto_por_bloque_y_permiso(): void
    {
        $sede = $this->sede('Sede Montos');
        $bloque = $this->bloque($sede, 'D');
        $ciclo = $this->ciclo();

        app(CuotasDelCicloService::class)->generar($ciclo, 1000, [$bloque->id => 2500], $this->admin());
        $this->assertSame(2500.0, (float) Cuota::query()->where('bloque_id', $bloque->id)->value('monto'));

        // Sin permiso de cuotas en toda la escuela no se generan.
        $profe = $this->usuario('Profe sin cuotas');
        $this->asignarRol($profe->persona, 'profesor', 'sede', $sede);
        $this->actingAs($profe->fresh())->post(route('ciclos.cuotas', $ciclo), ['monto' => 1000])->assertForbidden();
    }

    public function test_el_formulario_web_genera_las_cuotas(): void
    {
        $sede = $this->sede('Sede Web');
        $this->bloque($sede, 'E');
        $ciclo = $this->ciclo();

        $this->actingAs($this->admin())->post(route('ciclos.cuotas', $ciclo), ['monto' => 1200])->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(12, Cuota::query()->count());
    }
}
