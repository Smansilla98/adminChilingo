<?php

namespace Tests\Feature;

use App\Models\CoordinadorArea;
use App\Models\Profesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Los usuarios existentes conservan lo que podían hacer antes de la plataforma multirrol.
 * (Reemplaza a Tests\Unit\UserRolesLogicTest, que simulaba roles sin sede.)
 */
class AccesoParidadLegacyTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_direccion_equivale_a_admin(): void
    {
        $user = $this->usuario('Dirección');
        $user->forceFill(['role' => 'direccion'])->save();
        $user = $user->fresh();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isDireccion());
        $this->assertTrue($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertTrue($user->veTodosLosBloques());
        $this->assertSame('Administración', $user->etiquetaRol());
    }

    public function test_coordinador_sede_ve_reportes_pero_no_es_admin(): void
    {
        $quilmes = $this->sede('Quilmes');
        $user = $this->usuario('Coord');
        $this->rolDocenteEnSede($user->persona, $quilmes, 'coordinador');
        $user = $user->fresh();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isCoordinadorSede());
        $this->assertTrue($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertTrue($user->puedeVerLinkGestion('admin.reportes'));
        $this->assertTrue($user->puedeVerLinkGestion('admin.villa_gesell'));
        $this->assertFalse($user->puedeVerLinkGestion('admin.gastos'));
        $this->assertSame('Coordinación', $user->etiquetaRol());
    }

    public function test_coordinador_area_no_ve_reportes_ni_gastos(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $user = $this->usuario('Área');
        $profesor = $this->asignarDocente($user->persona, $bloque);
        CoordinadorArea::create(['profesor_id' => $profesor->id, 'area' => 'tambores']);
        $user = $user->fresh();

        $this->assertTrue($user->isCoordinadorArea());
        $this->assertFalse($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertTrue($user->puedeVerLinkGestion('programa'));
        $this->assertFalse($user->puedeVerLinkGestion('admin.reportes'));
        $this->assertFalse($user->puedeVerLinkGestion('admin.gastos'));
    }

    public function test_profesor_base_no_gestiona_panel(): void
    {
        $bloque = $this->bloque($this->sede('Banfield'));
        $user = $this->usuario('Profe');
        $this->asignarDocente($user->persona, $bloque);
        $user = $user->fresh();

        $this->assertTrue($user->isProfesor());
        $this->assertFalse($user->puedeGestionarOperativo());
        $this->assertFalse($user->puedeVerReportes());
        $this->assertSame('Profesor', $user->etiquetaRol());
        $this->assertSame([$bloque->id], $user->bloqueIdsPermitidos());
    }

    public function test_alumno_se_reconoce_por_role_legacy(): void
    {
        $user = $this->usuario('Alumna');
        $user->forceFill(['role' => 'alumno'])->save();
        $user = $user->fresh();

        $this->assertTrue($user->isAlumno());
        $this->assertFalse($user->isProfesor());
    }

    public function test_coordinacion_acota_por_sede_no_ve_toda_la_escuela(): void
    {
        $quilmes = $this->sede('Quilmes');
        $bloqueQ = $this->bloque($quilmes);

        $admin = $this->admin();

        $area = $this->usuario('Área');
        $prof = $this->asignarDocente($area->persona, $bloqueQ);
        CoordinadorArea::create(['profesor_id' => $prof->id, 'area' => 'género']);

        $coord = $this->usuario('Coord');
        $this->rolDocenteEnSede($coord->persona, $quilmes, 'coordinador');

        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloqueQ);

        $this->assertTrue($admin->veTodosLosBloques());
        $this->assertFalse($area->fresh()->veTodosLosBloques());
        $this->assertTrue($area->fresh()->acotaPorSede());
        $this->assertFalse($coord->fresh()->veTodosLosBloques());
        $this->assertTrue($coord->fresh()->acotaPorSede());
        $this->assertSame([$quilmes->id], $coord->fresh()->sedeIdsOperativas());
        $this->assertFalse($profe->fresh()->veTodosLosBloques());
        $this->assertFalse($profe->fresh()->acotaPorSede());
    }

    public function test_perfil_docente_legacy_vinculado_solo_por_user_id_sigue_funcionando(): void
    {
        $bloque = $this->bloque($this->sede('Varela'));
        $user = $this->usuario('Legacy');
        $profesor = Profesor::create(['user_id' => $user->id, 'nombre' => 'Legacy', 'activo' => true]);
        $profesor->forceFill(['persona_id' => null])->saveQuietly();
        $profesor->bloques()->attach($bloque->id, ['rol' => 'titular']);

        $this->assertTrue($user->fresh()->isProfesor());
        $this->assertTrue($user->fresh()->puedeAccederBloque($bloque->id));
    }
}
