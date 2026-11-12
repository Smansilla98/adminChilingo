<?php

namespace Tests\Feature;

use App\Domain\Personas\LegajoMenorService;
use App\Models\LegajoMenor;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Legajo de menores: tutor, uso de imagen, apto médico privado y aviso de vencimiento. */
class LegajoMenorTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function menor(int $anios = 12): Persona
    {
        $persona = $this->persona('Tomi Ríos', '45111222');
        $persona->update(['fecha_nacimiento' => now()->subYears($anios)->toDateString()]);

        return $persona->fresh();
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('apto.pdf', "%PDF-1.4\n%fake\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_un_menor_necesita_tutor_para_guardar_el_legajo(): void
    {
        $persona = $this->menor();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('personas.legajo.update', $persona), ['tutor_nombre' => ''])
            ->assertSessionHasErrors(['tutor_nombre', 'tutor_telefono', 'tutor_vinculo']);
        $this->assertSame(0, LegajoMenor::query()->count());
    }

    public function test_guarda_tutor_imagen_y_apto_privado(): void
    {
        Storage::fake('comprobantes');
        $persona = $this->menor();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('personas.legajo.update', $persona), [
            'tutor_nombre' => 'Marta Ríos', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1166660000',
            'uso_imagen' => '1', 'uso_imagen_fecha' => '2026-03-01',
            'apto_medico_vence' => now()->addDays(10)->toDateString(), 'apto_medico' => $this->pdf(),
        ])->assertRedirect(route('personas.legajo', $persona));

        $legajo = LegajoMenor::query()->where('persona_id', $persona->id)->sole();
        $this->assertSame('Marta Ríos', $legajo->tutor_nombre);
        $this->assertTrue($legajo->uso_imagen);
        $this->assertNotNull($legajo->apto_medico_path);
        Storage::disk('comprobantes')->assertExists($legajo->apto_medico_path);

        // El apto se descarga solo con permiso de ver alumnos.
        $this->actingAs($admin)->get(route('personas.legajo.apto', $persona))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_api_muestra_el_estado_del_apto(): void
    {
        Storage::fake('comprobantes');
        $persona = $this->menor();
        LegajoMenor::query()->create([
            'persona_id' => $persona->id, 'tutor_nombre' => 'Marta', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1',
            'apto_medico_vence' => now()->addDays(10)->toDateString(),
        ]);
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/v1/personas/{$persona->id}/legajo")->assertOk()
            ->assertJsonPath('data.es_menor', true)
            ->assertJsonPath('data.apto_medico.dias_para_vencer', 10)
            ->assertJsonPath('data.apto_medico.vencido', false);
    }

    public function test_el_aviso_de_vencimiento_se_manda_una_vez(): void
    {
        $persona = $this->menor();
        LegajoMenor::query()->create([
            'persona_id' => $persona->id, 'tutor_nombre' => 'Marta', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1',
            'apto_medico_vence' => now()->addDays(5)->toDateString(),
        ]);
        $this->admin(); // un administrador que recibe el aviso

        $this->assertSame(1, app(LegajoMenorService::class)->avisarVencimientos());
        $this->assertSame(0, app(LegajoMenorService::class)->avisarVencimientos(), 'no se repite para la misma fecha');
        $this->assertNotNull(LegajoMenor::query()->where('persona_id', $persona->id)->value('apto_aviso_enviado_el'));
    }

    public function test_apto_que_no_vence_pronto_no_avisa(): void
    {
        $persona = $this->menor();
        LegajoMenor::query()->create([
            'persona_id' => $persona->id, 'tutor_nombre' => 'Marta', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1',
            'apto_medico_vence' => now()->addDays(120)->toDateString(),
        ]);
        $this->admin();

        $this->assertSame(0, app(LegajoMenorService::class)->avisarVencimientos());
    }

    public function test_sin_permiso_de_alumnos_no_se_edita(): void
    {
        $persona = $this->menor();
        $alumno = $this->usuario('Sin permisos');

        $this->actingAs($alumno)->get(route('personas.legajo', $persona))->assertForbidden();
        $this->actingAs($alumno)->put(route('personas.legajo.update', $persona), ['tutor_nombre' => 'X', 'tutor_telefono' => '1', 'tutor_vinculo' => 'Y'])->assertForbidden();
    }

    public function test_apto_con_contenido_falso_se_rechaza(): void
    {
        Storage::fake('comprobantes');
        $persona = $this->menor();
        // Un archivo real con HTML dentro, nombrado .pdf: el contenido manda, no el nombre.
        $ruta = storage_path('framework/testing/apto-falso.pdf');
        @mkdir(dirname($ruta), 0775, true);
        file_put_contents($ruta, '<html><script>alert(1)</script></html>');
        $falso = new UploadedFile($ruta, 'apto.pdf', null, null, true);

        $this->actingAs($this->admin())->put(route('personas.legajo.update', $persona), [
            'tutor_nombre' => 'Marta', 'tutor_vinculo' => 'Madre', 'tutor_telefono' => '1', 'apto_medico' => $falso,
        ])->assertSessionHasErrors('apto_medico');
        @unlink($ruta);
        $this->assertSame(0, LegajoMenor::query()->whereNotNull('apto_medico_path')->count());
    }
}
