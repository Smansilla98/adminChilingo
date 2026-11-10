<?php

namespace Tests\Feature;

use App\Models\PartituraVersion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La recarga de partituras del deploy nunca pisa lo que la escuela editó en el editor. */
class PartiturasBootstrapTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function editada(): array
    {
        return PartituraScore::normalizar([
            'version' => 4, 'title' => 'Ochosi editado por la escuela', 'tempo' => 77,
            'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'surdo_grave']],
            'sections' => [['name' => 'Toque', 'measures' => [['voces' => ['surdo_grave' => [['dur' => 'q', 'stroke' => 'nota']]]]]]],
        ]);
    }

    private function ochosi(): ProgramaRitmo
    {
        return ProgramaRitmo::query()->where('nombre', 'Ochosi')->first()
            ?? ProgramaRitmo::query()->create(['slug' => 'ochosi', 'año' => 1, 'orden' => 2, 'nombre' => 'Ochosi', 'publicado' => true, 'medios' => []]);
    }

    public function test_recarga_los_toques_sin_ediciones_pero_no_los_editados(): void
    {
        $toque = $this->ochosi();

        $this->artisan('partituras:bootstrap', ['--force' => true])->assertSuccessful();
        $cargada = $toque->fresh()->mediosNormalizados()['partitura_score'];
        $this->assertTrue(PartituraScore::tieneGolpes($cargada));
        $this->assertNotSame(77, $cargada['tempo']);

        // La escuela publica su versión desde el editor.
        $editada = $this->editada();
        $toque->update(['medios' => array_merge($toque->fresh()->mediosNormalizados(), ['partitura_score' => $editada])]);
        PartituraVersion::query()->create(['programa_ritmo_id' => $toque->id, 'numero' => 1, 'score' => $editada, 'autor' => 'Profe']);

        $this->artisan('partituras:bootstrap', ['--force' => true])->assertSuccessful();
        $this->assertSame(77, $toque->fresh()->mediosNormalizados()['partitura_score']['tempo'], 'el deploy no pisa la edición');
        $this->assertSame(1, PartituraVersion::query()->where('programa_ritmo_id', $toque->id)->count());
    }

    public function test_pisar_ediciones_guarda_antes_lo_actual_como_version(): void
    {
        $toque = $this->ochosi();
        $editada = $this->editada();
        $toque->update(['medios' => array_merge($toque->mediosNormalizados(), ['partitura_score' => $editada])]);
        PartituraVersion::query()->create(['programa_ritmo_id' => $toque->id, 'numero' => 1, 'score' => $editada, 'autor' => 'Profe']);
        // Edición posterior sin versión (por ejemplo, al validar una marca de revisión).
        $actual = array_replace($editada, ['tempo' => 81]);
        $toque->update(['medios' => array_merge($toque->fresh()->mediosNormalizados(), ['partitura_score' => $actual])]);

        $this->artisan('partituras:bootstrap', ['--force' => true, '--pisar-ediciones' => true])->assertSuccessful();

        $this->assertNotSame(81, $toque->fresh()->mediosNormalizados()['partitura_score']['tempo']);
        $respaldo = PartituraVersion::query()->where('programa_ritmo_id', $toque->id)->orderByDesc('numero')->first();
        $this->assertSame(2, $respaldo->numero);
        $this->assertSame('Respaldo antes de recargar', $respaldo->autor);
        $this->assertSame(81, $respaldo->score['tempo']);
    }
}
