<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** La matriz vieja /accesos se retiró; las rutas de profesor solo miran permisos. */
class AccesosRetiradosTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** @return list<string> */
    private function rutasProfesor(): array
    {
        return [
            route('profesor.bloques'),
            route('profesor.alumnos'),
            route('profesor.pagos-cuotas.index'),
            route('profesor.eventos'),
            route('profesor.asistencias.create'),
            route('profesor.asistencias.matrix'),
        ];
    }

    public function test_profesor_entra_a_sus_pantallas_como_antes(): void
    {
        $sede = $this->sede('Sede Profe');
        $bloque = $this->bloque($sede);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        // Una matriz vieja guardada ya no oculta nada: manda el permiso.
        $profe->forceFill(['modulos_access' => ['profesor.mis_bloques' => false]])->saveQuietly();

        $this->actingAs($profe->fresh());
        foreach ($this->rutasProfesor() as $url) {
            $r = $this->get($url);
            $this->assertLessThan(400, $r->getStatusCode(), $url);
            $this->assertStringNotContainsString('/login', (string) $r->headers->get('Location'), $url);
        }
    }

    public function test_sin_permisos_no_entra(): void
    {
        $this->actingAs($this->usuario('Sin permisos'));
        foreach ($this->rutasProfesor() as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('profesor.asistencias.store'))->assertForbidden();
        $this->post(route('profesor.asistencias.matrix.update'))->assertForbidden();
    }

    public function test_alumno_no_entra_a_pantallas_de_profesor(): void
    {
        $sede = $this->sede('Sede Alumno');
        $bloque = $this->bloque($sede);
        $alumno = $this->usuario('Alumno');
        $this->inscribirAlumno($alumno->persona, $bloque);

        $this->actingAs($alumno->fresh());
        $this->get(route('profesor.asistencias.create'))->assertForbidden();
        $this->get(route('profesor.pagos-cuotas.index'))->assertForbidden();
    }

    public function test_la_matriz_vieja_redirige_a_usuarios(): void
    {
        $this->actingAs($this->admin());
        $this->get('/accesos')->assertRedirect(route('usuarios.index'));
        $this->post('/accesos', ['user_id' => 1])->assertStatus(405);
    }
}
