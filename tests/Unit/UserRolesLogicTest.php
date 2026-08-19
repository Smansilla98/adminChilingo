<?php

namespace Tests\Unit;

use App\Models\Asistencia;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRolesLogicTest extends TestCase
{
    private function userConRoles(string $legacyRole, array $spatieRoles): User
    {
        $user = new class extends User
        {
            /** @var list<string> */
            public array $rolesFake = [];

            public function hasRole($roles, ?string $guard = null): bool
            {
                foreach ((array) $roles as $role) {
                    if (in_array((string) $role, $this->rolesFake, true)) {
                        return true;
                    }
                }

                return false;
            }
        };
        $user->role = $legacyRole;
        $user->rolesFake = $spatieRoles;

        return $user;
    }

    public function test_direccion_equivale_a_admin(): void
    {
        $user = $this->userConRoles('direccion', ['direccion']);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isDireccion());
        $this->assertTrue($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertSame('Dirección', $user->etiquetaRol());
    }

    public function test_coordinador_sede_ve_reportes_pero_no_es_admin(): void
    {
        $user = $this->userConRoles('profesor', ['profesor', 'coordinador_sede']);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isCoordinadorSede());
        $this->assertTrue($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertTrue($user->puedeVerLinkGestion('admin.reportes'));
        $this->assertTrue($user->puedeVerLinkGestion('admin.villa_gesell'));
        $this->assertFalse($user->puedeVerLinkGestion('admin.gastos'));
        $this->assertSame('Coordinador de sede', $user->etiquetaRol());
    }

    public function test_coordinador_area_no_ve_reportes_ni_gastos(): void
    {
        $user = $this->userConRoles('profesor', ['profesor', 'coordinador_area']);
        $this->assertTrue($user->isCoordinadorArea());
        $this->assertFalse($user->puedeVerReportes());
        $this->assertTrue($user->puedeGestionarOperativo());
        $this->assertTrue($user->puedeVerLinkGestion('programa'));
        $this->assertFalse($user->puedeVerLinkGestion('admin.reportes'));
    }

    public function test_profesor_base_no_gestiona_panel(): void
    {
        $user = $this->userConRoles('profesor', ['profesor']);
        $this->assertTrue($user->isProfesor());
        $this->assertFalse($user->puedeGestionarOperativo());
        $this->assertFalse($user->puedeVerReportes());
        $this->assertSame('Profesor', $user->etiquetaRol());
    }

    public function test_asistencia_presente_helper(): void
    {
        $this->assertTrue(Asistencia::esPresente('presente'));
        $this->assertTrue(Asistencia::esPresente('tarde'));
        $this->assertFalse(Asistencia::esPresente('ausencia_injustificada'));
        $this->assertFalse(Asistencia::esPresente('feriado'));
    }

    public function test_alumno_se_reconoce_por_role_legacy(): void
    {
        $user = $this->userConRoles('alumno', []);
        $this->assertTrue($user->isAlumno());
        $this->assertFalse($user->isProfesor());
        $this->assertSame('Alumno', $user->etiquetaRol());
    }

    public function test_coordinacion_acota_por_sede_no_ve_toda_la_escuela(): void
    {
        $admin = $this->userConRoles('admin', ['admin']);
        $area = $this->userConRoles('profesor', ['profesor', 'coordinador_area']);
        $sede = $this->userConRoles('profesor', ['profesor', 'coordinador_sede']);
        $profe = $this->userConRoles('profesor', ['profesor']);

        $this->assertTrue($admin->veTodosLosBloques());
        $this->assertFalse($area->veTodosLosBloques());
        $this->assertTrue($area->acotaPorSede());
        $this->assertFalse($sede->veTodosLosBloques());
        $this->assertTrue($sede->acotaPorSede());
        $this->assertFalse($profe->veTodosLosBloques());
        $this->assertFalse($profe->acotaPorSede());
    }
}
