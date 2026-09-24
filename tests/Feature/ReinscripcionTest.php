<?php

namespace Tests\Feature;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Alumno;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Un alumno que ya existe se inscribe en otro bloque: tiene que seguir siendo UNA ficha.
 */
class ReinscripcionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function datosAlta(int $sedeId, int $bloqueId, string $dni): array
    {
        return [
            'nombre_apellido' => 'Ana Pérez', 'dni' => $dni, 'fecha_nacimiento' => '2000-01-01',
            'instrumento_principal' => 'Repique', 'sede_id' => $sedeId, 'bloque_ids' => [$bloqueId], 'activo' => '1',
        ];
    }

    public function test_alta_con_el_mismo_dni_en_otro_formato_no_duplica_la_ficha(): void
    {
        $admin = $this->admin();
        $banfield = $this->sede('Banfield');
        $palomar = $this->sede('Palomar');
        $b1 = $this->bloque($banfield, 'B1');
        $p1 = $this->bloque($palomar, 'P1');

        $this->actingAs($admin)->post(route('alumnos.store'), $this->datosAlta($banfield->id, $b1->id, '30.123.456'))->assertRedirect();
        $this->actingAs($admin)->post(route('alumnos.store'), $this->datosAlta($palomar->id, $p1->id, '30123456'))
            ->assertSessionHasErrors('dni');

        $this->assertSame(1, Alumno::query()->count());
    }

    public function test_agregar_el_bloque_en_la_ficha_existente_mantiene_una_cuenta_corriente(): void
    {
        $banfield = $this->sede('Banfield');
        $palomar = $this->sede('Palomar');
        $persona = $this->persona('Ana', '30123456');
        $alumno = $this->inscribirAlumno($persona, $this->bloque($banfield, 'B1'));
        $this->inscribirAlumno($persona, $this->bloque($palomar, 'P1'));
        Cuota::query()->create(['nombre' => 'General marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);

        $this->assertSame(1, $persona->alumnos()->count());
        $this->assertSame(2, $alumno->fresh()->bloques()->count());
        // La cuota general se cobra una sola vez aunque curse en dos bloques.
        $this->assertCount(1, app(EstadoCuentaService::class)->paraAlumno($alumno->fresh())['items']);
    }

    public function test_importacion_reusa_la_ficha_aunque_el_dni_tenga_puntos(): void
    {
        $sede = $this->sede('Quilmes');
        $b1 = $this->bloque($sede, 'Q1');
        $existente = Alumno::query()->create(['nombre_apellido' => 'Ana Pérez', 'dni' => '30.123.456', 'sede_id' => $sede->id, 'activo' => true]);

        $this->assertSame($existente->id, app(\App\Domain\Personas\PersonaService::class)->alumnoPorDni('30123456')?->id);
    }
}
