<?php

namespace Tests\Feature;

use App\Models\BloqueHorario;
use App\Models\Evento;
use App\Models\Sede;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión del calendario: vista mensual (talleres fijos, eventos y shows) y API para FullCalendar.
 */
class CalendarioWebTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function escenario(): void
    {
        $sede = $this->sede('Centro');
        $otra = $this->sede('Norte');
        $bloque = $this->bloque($sede, 'Trinchera');
        // Viernes (5) 18:00 a 20:00: marzo 2026 tiene viernes 6, 13, 20 y 27.
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 5, 'hora_inicio' => '18:00', 'hora_fin' => '20:00']);

        Evento::query()->create(['titulo' => 'Ensayo general', 'fecha' => '2026-03-10', 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'sede_id' => $sede->id, 'tipo_evento' => 'ensayo']);
        Evento::query()->create(['titulo' => 'Reunión Norte', 'fecha' => '2026-03-11', 'hora_inicio' => '09:00', 'sede_id' => $otra->id, 'tipo_evento' => 'reunion']);
        Show::query()->create(['titulo' => 'Show Marzo', 'fecha' => '2026-03-14', 'hora_inicio' => '21:00']);
    }

    public function test_la_vista_mensual_arma_dias_y_listado(): void
    {
        $this->escenario();

        $this->actingAs($this->admin())->get(route('calendario.index', ['year' => 2026, 'month' => 3]))
            ->assertOk()
            ->assertViewHas('year', 2026)
            ->assertViewHas('month', 3)
            ->assertViewHas('daysInMonth', 31)
            ->assertViewHas('eventsByDay', function (array $dias) {
                return count($dias['2026-03-06']) === 1
                    && $dias['2026-03-06'][0]['type'] === 'bloque_taller'
                    && $dias['2026-03-10'][0]['type'] === 'evento'
                    && $dias['2026-03-14'][0]['type'] === 'show';
            })
            ->assertViewHas('listItems', fn ($items) => $items->count() === 7);
    }

    public function test_la_api_de_eventos_combina_eventos_shows_y_talleres(): void
    {
        $this->escenario();

        $todos = $this->actingAs($this->admin())->getJson('/calendario/eventos?start=2026-03-01&end=2026-03-31')->assertOk()->json();
        $this->assertCount(7, $todos);
        $this->assertSame(4, collect($todos)->where('extendedProps.tipo', 'taller_fijo')->count());

        $desdeNorte = $this->actingAs($this->admin())
            ->getJson('/calendario/eventos?start=2026-03-01&end=2026-03-31&sede_id='.Sede::query()->where('nombre', 'Norte')->value('id'))
            ->assertOk()->json();
        $this->assertCount(6, $desdeNorte);
        $this->assertSame(['Reunión Norte', 'Show Marzo'], collect($desdeNorte)->pluck('title')->reject(fn ($t) => str_starts_with($t, 'Taller:'))->values()->all());
    }
}
