<?php

namespace Tests\Feature;

use App\Domain\Asistencias\AusenciasSeguidas;
use App\Domain\Datos\Respaldo;
use App\Domain\Partituras\RevisionPartituras;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\PartituraRevision;
use App\Models\ProgramaRitmo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Backups, aviso de alumnos que dejan de venir, revisión de partituras y
 * tendencias/deuda en reportes.
 */
class MejorasOperativasTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
    }

    // ── Backups ──────────────────────────────────────────────────────────────

    public function test_el_backup_se_guarda_en_el_disco_persistente_y_poda_los_viejos(): void
    {
        $this->sede('Palomar');

        $this->artisan('chilinga:respaldo', ['--conservar' => 2])->assertSuccessful();
        $this->travel(1)->seconds();
        $this->artisan('chilinga:respaldo', ['--conservar' => 2])->assertSuccessful();
        $this->travel(1)->seconds();
        $this->artisan('chilinga:respaldo', ['--conservar' => 2])->assertSuccessful();

        $archivos = Respaldo::archivos();
        $this->assertCount(2, $archivos);
        $sql = gzdecode(Storage::disk('comprobantes')->get($archivos[0]));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('Palomar', $sql);
        $this->assertTrue(Respaldo::ultimo()['ok']);
    }

    public function test_estado_del_sistema_muestra_el_backup_y_solo_admin_lo_descarga(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('estado-sistema'))
            ->assertOk()->assertSee('Backups de la base de datos')->assertSee('Todavía no se hizo ningún backup');

        $this->artisan('chilinga:respaldo')->assertSuccessful();

        $this->actingAs($admin)->get(route('estado-sistema'))->assertOk()->assertSee('Descargar el último backup');
        $this->actingAs($admin)->get(route('estado-sistema.respaldo'))->assertOk()->assertDownload();
        $this->actingAs($this->usuario('Curioso'))->get(route('estado-sistema.respaldo'))->assertForbidden();
    }

    // ── Ausencias seguidas ───────────────────────────────────────────────────

    /** @return array{0: Bloque, 1: Alumno, 2: User} */
    private function bloqueConAlumnoYProfe(): array
    {
        $bloque = $this->bloque($this->sede('Banfield'), 'Bloque Banfield');
        $profe = $this->usuario('Profe Banfield', $this->persona('Profe Banfield'));
        $this->asignarDocente($profe->persona, $bloque);
        $alumno = $this->inscribirAlumno($this->persona('Ana Gómez'), $bloque);
        $alumno->update(['telefono' => '11 5555-1234']);

        return [$bloque, $alumno->fresh(), $profe];
    }

    private function clase(Alumno $alumno, Bloque $bloque, int $haceDias, string $tipo): void
    {
        Asistencia::query()->create([
            'alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => now()->subDays($haceDias)->toDateString(),
            'presente' => Asistencia::esPresente($tipo), 'tipo_asistencia' => $tipo,
        ]);
    }

    public function test_detecta_tres_faltas_seguidas_e_ignora_justificadas_y_feriados(): void
    {
        [$bloque, $alumno] = $this->bloqueConAlumnoYProfe();
        $this->clase($alumno, $bloque, 28, 'presente');
        $this->clase($alumno, $bloque, 21, 'ausencia_injustificada');
        $this->clase($alumno, $bloque, 14, 'feriado');
        $this->clase($alumno, $bloque, 10, 'ausencia_justificada');
        $this->clase($alumno, $bloque, 7, 'ausencia_injustificada');
        $this->clase($alumno, $bloque, 1, 'ausencia_injustificada');

        $rachas = app(AusenciasSeguidas::class)->enBloques([$bloque->id]);

        $this->assertCount(1, $rachas);
        $this->assertSame(3, $rachas[0]['faltas']);
        $this->assertSame(now()->subDays(21)->toDateString(), $rachas[0]['desde']->toDateString());
        $this->assertSame(now()->subDays(28)->toDateString(), $rachas[0]['ultima_presencia']->toDateString());
    }

    public function test_una_presencia_corta_la_racha(): void
    {
        [$bloque, $alumno] = $this->bloqueConAlumnoYProfe();
        $this->clase($alumno, $bloque, 21, 'ausencia_injustificada');
        $this->clase($alumno, $bloque, 14, 'ausencia_injustificada');
        $this->clase($alumno, $bloque, 7, 'tarde');
        $this->clase($alumno, $bloque, 1, 'ausencia_injustificada');

        $this->assertCount(0, app(AusenciasSeguidas::class)->enBloques([$bloque->id]));
    }

    public function test_avisa_al_docente_una_sola_vez_y_lo_muestra_en_su_inicio(): void
    {
        [$bloque, $alumno, $profe] = $this->bloqueConAlumnoYProfe();
        foreach ([21, 14, 7] as $d) {
            $this->clase($alumno, $bloque, $d, 'ausencia_injustificada');
        }

        $this->artisan('chilinga:avisos', ['tipo' => 'ausencias'])->expectsOutput('Avisos nuevos: 1')->assertSuccessful();
        $this->artisan('chilinga:avisos', ['tipo' => 'ausencias'])->expectsOutput('Avisos nuevos: 0')->assertSuccessful();
        $this->assertSame(1, $profe->fresh()->notifications()->count());

        $this->actingAs($profe->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Para contactar')
            ->assertSee('Ana Gómez')
            ->assertSee('https://wa.me/541155551234', false);
    }

    // ── Revisión de partituras ───────────────────────────────────────────────

    private function toqueConMarca(): ProgramaRitmo
    {
        $score = [
            'version' => 4, 'title' => 'Toque X', 'tempo' => 86,
            'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'repique']],
            'sections' => [[
                'id' => 's1', 'name' => 'TOQUE', 'repeatX' => 1,
                'measures' => [
                    ['id' => 'm1', 'texto' => null, 'voces' => []],
                    ['id' => 'm2', 'texto' => 'Revisar: acentos del repique', 'voces' => []],
                ],
            ]],
        ];

        return ProgramaRitmo::query()->create([
            'año' => 1, 'orden' => 1, 'nombre' => 'Toque X', 'slug' => 'toque-x',
            'medios' => ['partitura_score' => $score],
        ]);
    }

    public function test_lista_los_pasajes_y_validar_quita_la_marca_y_la_recuerda(): void
    {
        $toque = $this->toqueConMarca();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('programa.revision'))
            ->assertOk()->assertSee('Toque X')->assertSee('acentos del repique')->assertSee('Compás 2');

        $this->actingAs($admin)->post(route('programa.revision.validar', $toque->id), [
            'seccion' => 'TOQUE', 'compas' => 2, 'nota' => 'acentos del repique',
        ])->assertRedirect();

        $this->assertSame([], RevisionPartituras::marcas($toque->fresh()->mediosNormalizados()['partitura_score']));
        $this->assertSame(1, PartituraRevision::query()->count());
        $this->actingAs($admin)->get(route('programa.revision'))->assertSee('No queda nada por revisar');

        // La recarga desde los JSON vuelve a traer la marca: se aplica la validación guardada.
        $recargado = RevisionPartituras::aplicarValidadas([
            'sections' => [['name' => 'TOQUE', 'measures' => [['texto' => null], ['texto' => 'Revisar: acentos del repique']]]],
        ], $toque->id);
        $this->assertNull($recargado['sections'][0]['measures'][1]['texto']);
    }

    public function test_solo_quien_administra_partituras_entra_a_revision(): void
    {
        $this->actingAs($this->usuario('Alumno curioso'))->get(route('programa.revision'))->assertForbidden();
    }

    // ── Claves foráneas ──────────────────────────────────────────────────────

    public function test_la_base_no_deja_borrar_un_alumno_con_pagos(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('La regla RESTRICT se aplica solo en MySQL.');
        }
        $bloque = $this->bloque($this->sede('Varela'));
        $alumno = $this->inscribirAlumno($this->persona('Con Pagos'), $bloque);
        $cuota = Cuota::query()->create(['bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'C', 'año' => now()->year, 'mes' => now()->month, 'monto' => 100, 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now()->toDateString(), 'monto_total' => 100]);
        PagoDetalle::query()->create(['pago_id' => $pago->id, 'alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 100]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('alumnos')->where('id', $alumno->id)->delete();
    }

    // ── Tendencias y deuda ───────────────────────────────────────────────────

    public function test_tendencias_compara_meses_y_muestra_la_deuda_por_bloque(): void
    {
        $sede = $this->sede('Quilmes');
        $bloque = $this->bloque($sede, 'Bloque Quilmes');
        $paga = $this->inscribirAlumno($this->persona('Paga Siempre'), $bloque);
        $this->inscribirAlumno($this->persona('Debe Todo'), $bloque);
        $mes = now()->subMonth();
        $cuota = Cuota::query()->create([
            'bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Cuota pasada',
            'año' => $mes->year, 'mes' => $mes->month, 'monto' => 10000, 'activo' => true,
            'fecha_vencimiento' => $mes->copy()->endOfMonth()->toDateString(),
        ]);
        $pago = Pago::query()->create(['fecha_pago' => $mes->copy()->startOfMonth()->addDays(5)->toDateString(), 'monto_total' => 10000]);
        PagoDetalle::query()->create(['pago_id' => $pago->id, 'alumno_id' => $paga->id, 'cuota_id' => $cuota->id, 'monto' => 10000]);
        Gasto::query()->create(['sede_id' => $sede->id, 'fecha' => $mes->copy()->startOfMonth()->addDays(2)->toDateString(), 'tipo' => 'alquiler', 'monto' => 4000]);

        $this->actingAs($this->admin())->get(route('reportes.tendencias', ['mes' => $mes->month, 'año' => $mes->year, 'meses' => 6]))
            ->assertOk()
            ->assertSee('$ 10.000')      // cobrado del mes
            ->assertSee('$ 6.000')       // resultado: 10.000 - 4.000
            ->assertSee('Bloque Quilmes')
            ->assertSee('Debe Todo')
            ->assertDontSee('Paga Siempre');
    }
}
