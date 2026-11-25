<?php

namespace Tests\Feature;

use App\Models\Cuota;
use App\Support\PagoCuotaToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** El paso "otros bloques" del formulario público: cuotas pendientes del alumno en otros bloques del mismo mes. */
class ComprobanteOtrosBloquesTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_devuelve_las_cuotas_pendientes_de_los_otros_bloques_del_alumno(): void
    {
        $sede = $this->sede('Sede Otros');
        $bloque1 = $this->bloque($sede, 'Bloque A');
        $bloque2 = $this->bloque($sede, 'Bloque B');
        foreach ([$bloque1, $bloque2] as $b) {
            Cuota::query()->create(['bloque_id' => $b->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 1500, 'activo' => true]);
        }

        $persona = $this->persona('Lucía', '30999888');
        $alumno = $this->inscribirAlumno($persona->fresh(), $bloque1);
        $alumno->update(['dni' => '30999888', 'nombre_apellido' => 'Lucía Gómez']);
        $alumno->bloques()->attach($bloque2->id);

        $token = PagoCuotaToken::emitir((int) $alumno->id, (int) $sede->id);

        $this->getJson(route('comprobante-cuota-public.api.alumno-otros-bloques', [
            'alumno_id' => $alumno->id,
            'lookup_token' => $token,
            'sede_id' => $sede->id,
            'año' => 2026,
            'mes' => 3,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'bloques_cuotas')
            ->assertJsonFragment(['bloque_nombre' => 'Bloque B', 'monto' => 1500.0]);
    }

    public function test_un_token_de_otra_busqueda_no_sirve(): void
    {
        $sede = $this->sede('Sede Token');
        $bloque = $this->bloque($sede, 'Bloque T');
        $alumno = $this->inscribirAlumno($this->persona('Marta', '30555444')->fresh(), $bloque);

        $this->getJson(route('comprobante-cuota-public.api.alumno-otros-bloques', [
            'alumno_id' => $alumno->id,
            'lookup_token' => 'token-invalido',
            'sede_id' => $sede->id,
            'año' => 2026,
            'mes' => 3,
        ]))->assertStatus(422);
    }
}
