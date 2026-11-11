<?php

namespace Tests\Feature;

use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class CuotasLoteTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function datos(array $extra = []): array
    {
        $palomar = $this->sede('Palomar');
        $b1 = $this->bloque($palomar, 'Martes');
        $b2 = $this->bloque($palomar, 'Jueves');

        return $extra + [
            'nombre' => 'Cuota mensual', 'año' => 2027, 'mes_desde' => 1, 'mes_hasta' => 3,
            'monto' => 25000, 'alcance' => 'bloque', 'bloque_ids' => [$b1->id, $b2->id], 'dia_vencimiento' => 10,
        ];
    }

    public function test_vista_previa_no_escribe_y_generar_no_duplica(): void
    {
        $admin = $this->admin();
        $datos = $this->datos();

        $previa = $this->actingAs($admin)->postJson('/api/v1/cuotas/lote/vista-previa', $datos)->assertOk()
            ->assertJsonPath('data.total', 6)->assertJsonPath('data.crear', 6)->json('data');
        $this->assertSame(0, Cuota::query()->count(), 'la vista previa no crea nada');
        $this->assertSame('10/02/2027', \Carbon\Carbon::parse($previa['items'][1]['vencimiento'])->format('d/m/Y'), 'febrero vence el 10');

        $this->actingAs($admin)->postJson('/api/v1/cuotas/lote', $datos)->assertCreated()->assertJsonPath('data.creadas', 6);
        $this->assertSame(6, Cuota::query()->count());

        // Segunda corrida: todo ya existe, no se duplica nada.
        $this->actingAs($admin)->postJson('/api/v1/cuotas/lote/vista-previa', $datos)->assertOk()
            ->assertJsonPath('data.crear', 0)->assertJsonPath('data.existen', 6);
        $this->actingAs($admin)->postJson('/api/v1/cuotas/lote', $datos)->assertCreated()->assertJsonPath('data.creadas', 0);
        $this->assertSame(6, Cuota::query()->count());
    }

    public function test_dia_de_vencimiento_se_ajusta_al_ultimo_dia_del_mes(): void
    {
        $datos = $this->datos(['año' => 2027, 'mes_desde' => 2, 'mes_hasta' => 2, 'dia_vencimiento' => 31, 'alcance' => 'general']);
        unset($datos['bloque_ids']);
        $this->actingAs($this->admin())->postJson('/api/v1/cuotas/lote/vista-previa', $datos)->assertOk()
            ->assertJsonPath('data.items.0.vencimiento', '2027-02-28');
    }

    public function test_rango_invertido_y_demasiadas_cuotas_se_rechazan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/v1/cuotas/lote/vista-previa', $this->datos(['mes_desde' => 6, 'mes_hasta' => 2]))
            ->assertStatus(422)->assertJsonValidationErrors('mes_hasta');

        $bloques = collect(range(1, 50))->map(fn ($i) => $this->bloque($this->sede("Sede {$i}"), "B{$i}")->id)->all();
        $this->actingAs($admin)->postJson('/api/v1/cuotas/lote/vista-previa', $this->datos(['bloque_ids' => $bloques, 'mes_desde' => 1, 'mes_hasta' => 12]))
            ->assertStatus(422)->assertJsonValidationErrors('mes_hasta');
    }

    public function test_sin_permiso_de_cuotas_es_403(): void
    {
        $tesorera = $this->usuario('Contadora');
        $this->asignarRol($tesorera->persona, 'contador');
        Sanctum::actingAs($tesorera->fresh());
        $this->postJson('/api/v1/cuotas/lote', $this->datos())->assertForbidden();
    }

    public function test_web_vista_previa_y_generar(): void
    {
        $admin = $this->admin();
        $datos = $this->datos();
        $this->actingAs($admin)->get(route('cuotas.lote.create'))->assertOk()->assertSee('Generar cuotas por lote');

        $this->actingAs($admin)->post(route('cuotas.lote.store'), $datos + ['accion' => 'previa'])
            ->assertOk()->assertSee('Vista previa: 6 cuotas');
        $this->assertSame(0, Cuota::query()->count());

        $this->actingAs($admin)->post(route('cuotas.lote.store'), $datos + ['accion' => 'generar'])
            ->assertRedirect(route('cuotas.index'));
        $this->assertSame(6, Cuota::query()->count());
    }
}
