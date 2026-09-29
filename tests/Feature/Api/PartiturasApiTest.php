<?php

namespace Tests\Feature\Api;

use App\Models\ProgramaRitmo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class PartiturasApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_detalle_no_enlaza_al_panel_y_el_pdf_sale_por_la_api(): void
    {
        config(['app.url' => 'https://admin-chilingo.up.railway.app']);
        Storage::fake('comprobantes');
        Storage::disk('comprobantes')->put('partituras/samba.pdf', '%PDF-1.4');

        $persona = $this->persona('Ana');
        $user = $this->usuario('Ana', $persona);
        $this->asignarRol($persona, 'alumno');

        ProgramaRitmo::query()->create([
            'slug' => 'samba',
            'año' => 1,
            'orden' => 1,
            'nombre' => 'Samba',
            'autor' => 'Tradicional',
            'publicado' => true,
            'medios' => [
                'partitura' => ['path' => 'partituras/samba.pdf', 'nombre' => 'samba.pdf'],
                'partitura_score' => [
                    'version' => 4,
                    'tempo' => 100,
                    'timeSignature' => ['num' => 4, 'den' => 4],
                    'instruments' => [['id' => 'surdo_grave']],
                    'sections' => [[
                        'name' => 'Llamada',
                        'repeatX' => 2,
                        'measures' => [[
                            'voces' => [
                                'surdo_grave' => [
                                    ['dur' => 'q', 'rest' => false, 'stroke' => 'nota'],
                                    ['dur' => 'q', 'rest' => true, 'stroke' => 'nota'],
                                    ['dur' => 'q', 'rest' => false, 'stroke' => 'acentuado'],
                                ],
                            ],
                        ]],
                    ]],
                ],
                'videos_base' => [
                    'surdo_grave' => ['url' => 'https://admin-chilingo.up.railway.app/toque/samba'],
                    'surdo_agudo' => ['url' => 'https://www.youtube.com/watch?v=abcdefghijk'],
                ],
            ],
        ]);

        Sanctum::actingAs($user->fresh());

        $r = $this->getJson('/api/v1/partituras/samba')->assertOk();
        $json = $r->json();
        $this->assertArrayNotHasKey('visor_url', $json);
        $this->assertArrayNotHasKey('pdf_url', $json);
        $r->assertJsonPath('tiene_pdf', true)
            ->assertJsonPath('lectura.compas', '4/4')
            ->assertJsonPath('lectura.secciones.0.nombre', 'Llamada')
            ->assertJsonPath('lectura.secciones.0.compases.0.voces.surdo_grave', '● · > ·')
            ->assertJsonCount(1, 'videos')
            ->assertJsonPath('videos.0.url', 'https://www.youtube.com/watch?v=abcdefghijk');

        $this->get('/api/v1/partituras/samba/archivo')->assertOk();
    }

    public function test_el_admin_crea_escribe_y_escucha_por_la_api_y_un_alumno_no_edita(): void
    {
        $ajeno = $this->persona('Ana');
        $alumno = $this->usuario('Ana', $ajeno);
        $this->asignarRol($ajeno, 'alumno');
        Sanctum::actingAs($alumno->fresh());
        $this->postJson('/api/v1/partituras', ['nombre' => 'No', 'anio' => 1])->assertForbidden();

        $persona = $this->persona('Dire');
        $admin = $this->usuario('Dire', $persona);
        $this->asignarRol($persona, 'administrador');
        Sanctum::actingAs($admin->fresh());

        $alta = $this->postJson('/api/v1/partituras', [
            'nombre' => 'Toque de prueba',
            'anio' => 2,
            'autor' => 'Bloque',
        ])->assertCreated();
        $slug = $alta->json('slug');
        $this->assertIsString($slug);

        $this->putJson('/api/v1/partituras/'.$slug.'/score', [
            'score' => [
                'tempo' => 84,
                'timeSignature' => ['num' => 4, 'den' => 4],
                'instruments' => [['id' => 'surdo_grave'], ['id' => 'repique']],
                'sections' => [[
                    'name' => 'Llamada',
                    'repeatX' => 2,
                    'measures' => [[
                        'voces' => [
                            'surdo_grave' => [
                                ['dur' => 'q', 'rest' => false, 'stroke' => 'nota'],
                                ['dur' => 'q', 'rest' => true],
                                ['dur' => 'q', 'rest' => true],
                                ['dur' => 'q', 'rest' => true],
                            ],
                        ],
                    ]],
                ]],
            ],
        ])->assertOk()
            ->assertJsonPath('lectura.secciones.0.repetir', 2)
            ->assertJsonPath('lectura.secciones.0.compases.0.voces.surdo_grave', '● · · ·');

        $this->getJson('/api/v1/partituras/'.$slug)
            ->assertOk()
            ->assertJsonPath('publicado', false)
            ->assertJsonPath('score.sections.0.name', 'Llamada');

        Sanctum::actingAs($alumno->fresh());
        $this->getJson('/api/v1/partituras/'.$slug)->assertNotFound();

        Sanctum::actingAs($admin->fresh());
        $muestras = $this->getJson('/api/v1/partituras/muestras')->assertOk()->json('data');
        $this->assertCount(27, $muestras);
        $this->get('/api/v1/partituras/muestras/surdo_grave_normal.wav')->assertOk();
        $this->getJson('/api/v1/partituras/muestras/no_existe.wav')->assertNotFound();

        $this->deleteJson('/api/v1/partituras/'.$slug)->assertOk();
        $this->getJson('/api/v1/partituras/'.$slug)->assertNotFound();
    }
}
