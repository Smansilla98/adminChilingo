<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría de permisos: lo que no corresponde no aparece en el menú y no se puede abrir por URL directa.
 */
class PermisosMenuAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Pantallas de gestión económica / administrativa. */
    private const ADMIN_RUTAS = [
        'pagos.index', 'gastos.index', 'morosos.index', 'cuotas.index', 'reportes.index',
        'direccion.index', 'liquidaciones.index', 'alumnos.index', 'bloques.index', 'asistencias.index',
    ];

    /** @return list<string> */
    private function hrefsDelMenu(string $html): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function test_alumno_no_ve_ni_abre_pantallas_de_administracion(): void
    {
        $alumno = $this->inscribirAlumno($this->persona('Luz Alumna'), $this->bloque($this->sede('Palomar')));
        $user = $this->usuario('Luz Alumna', $alumno->persona);
        $this->asignarRol($alumno->persona, 'alumno');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $hrefs = $this->hrefsDelMenu($html);
        foreach (self::ADMIN_RUTAS as $ruta) {
            $this->assertNotContains(route($ruta), $hrefs, "El alumno ve «{$ruta}» en el menú");
        }

        foreach (self::ADMIN_RUTAS as $ruta) {
            $status = $this->actingAs($user)->get(route($ruta))->getStatusCode();
            $this->assertNotSame(200, $status, "El alumno abre «{$ruta}» por URL directa");
        }
    }

    public function test_profesor_solo_ve_pagos_de_su_bloque_y_no_gestion_global(): void
    {
        $sede = $this->sede('Palomar');
        $bloqueSuyo = $this->bloque($sede, 'Lunes');
        $bloqueAjeno = $this->bloque($sede, 'Miércoles');
        $persona = $this->persona('Pablo Profe');
        $this->asignarDocente($persona, $bloqueSuyo);
        $this->asignarRol($persona, 'profesor', 'bloque', null, $bloqueSuyo);
        $user = $this->usuario('Pablo Profe', $persona);

        $alumnoSuyo = $this->inscribirAlumno($this->persona('Alumna Suya'), $bloqueSuyo);
        $alumnoAjeno = $this->inscribirAlumno($this->persona('Alumno Ajeno'), $bloqueAjeno);
        $cuotaSuya = \App\Models\Cuota::query()->create(['nombre' => 'Mensual', 'año' => (int) now()->year, 'mes' => 1, 'monto' => 1000, 'alcance' => 'bloque', 'bloque_id' => $bloqueSuyo->id, 'activo' => true]);
        $cuotaAjena = \App\Models\Cuota::query()->create(['nombre' => 'Mensual', 'año' => (int) now()->year, 'mes' => 1, 'monto' => 1000, 'alcance' => 'bloque', 'bloque_id' => $bloqueAjeno->id, 'activo' => true]);
        $pagoSuyo = \App\Models\Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pagoSuyo->detalles()->create(['alumno_id' => $alumnoSuyo->id, 'cuota_id' => $cuotaSuya->id, 'monto' => 1000]);
        $pagoAjeno = \App\Models\Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pagoAjeno->detalles()->create(['alumno_id' => $alumnoAjeno->id, 'cuota_id' => $cuotaAjena->id, 'monto' => 1000]);

        // Pagos de cuotas: el rol lo permite, pero solo con su alcance de bloque.
        // Listado general de pagos: el alcance de bloque no le da sede, así que no ve ninguno.
        $this->actingAs($user)->get(route('pagos.index'))->assertOk()
            ->assertDontSee('Alumna Suya')->assertDontSee('Alumno Ajeno');
        // Su pantalla de cobro (la del menú) muestra solo los alumnos de su bloque.
        $this->actingAs($user)->get(route('profesor.pagos-cuotas.index'))->assertOk()
            ->assertSee('Alumna Suya')->assertDontSee('Alumno Ajeno');
        $this->actingAs($user)->get(route('pagos.show', $pagoAjeno))->assertForbidden();

        // Morosos: hay un moroso en el bloque ajeno; el profesor no debe verlo.
        \App\Models\Cuota::query()->create(['nombre' => 'Vencida', 'año' => (int) now()->year, 'mes' => 1, 'monto' => 1000, 'alcance' => 'bloque', 'bloque_id' => $bloqueAjeno->id, 'activo' => true, 'fecha_vencimiento' => now()->subDays(20)->toDateString()]);
        $this->actingAs($user)->get(route('morosos.index'))->assertOk()->assertDontSee('Alumno Ajeno');

        // Padrón de alumnos (permiso alumnos.view): solo los de su bloque.
        $this->actingAs($user)->get(route('alumnos.index'))->assertOk()->assertSee('Alumna Suya')->assertDontSee('Alumno Ajeno');

        foreach (['gastos.index', 'reportes.index', 'direccion.index', 'liquidaciones.index'] as $ruta) {
            $status = $this->actingAs($user)->get(route($ruta))->getStatusCode();
            $this->assertNotSame(200, $status, "El profesor abre «{$ruta}» por URL directa");
        }
    }

    public function test_admin_ve_y_abre_todo_el_menu_de_administracion(): void
    {
        $admin = $this->admin('Dirección');
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        // Los accesos viven en el menú lateral o en la página de su módulo (las tarjetas del inicio llevan a ella).
        $hrefs = $this->hrefsDelMenu($html);
        preg_match_all('#href="(http://localhost/modulos/[^"]+)"#', $html, $m);
        foreach (array_unique($m[1]) as $paginaModulo) {
            $hrefs = array_merge($hrefs, $this->hrefsDelMenu($this->actingAs($admin)->get($paginaModulo)->assertOk()->getContent()));
        }

        foreach (['pagos.index', 'gastos.index', 'morosos.index', 'cuotas.index', 'reportes.index', 'direccion.index', 'alumnos.index', 'bloques.index'] as $ruta) {
            $this->assertContains(route($ruta), $hrefs, "El admin no ve «{$ruta}» en el menú");
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
    }
}
