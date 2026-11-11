<?php

namespace Tests\Feature\Api;

use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\Dispositivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Endpoints que agregó la app móvil (Equipo D): mi asistencia, sesiones de otra cuenta, avisos paginados. */
class AppMovilApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_alumno_ve_su_asistencia_por_bloque_y_mes_y_su_horario(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'), 'Martes');
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '21:00']);
        $user = $this->usuario('Alumna');
        $alumno = $this->inscribirAlumno($user->persona, $bloque);
        $anio = (int) now()->year;
        foreach (['presente' => "$anio-03-03", 'tarde' => "$anio-03-10", 'ausencia_injustificada' => "$anio-03-17", 'feriado' => "$anio-03-24", 'ausencia_justificada' => "$anio-04-07"] as $tipo => $fecha) {
            Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => $fecha, 'tipo_asistencia' => $tipo]);
        }
        // Otro alumno: no se ve.
        $otro = $this->inscribirAlumno($this->usuario('Otro')->persona, $bloque);
        Asistencia::query()->create(['alumno_id' => $otro->id, 'bloque_id' => $bloque->id, 'fecha' => "$anio-03-03", 'tipo_asistencia' => 'presente']);

        Sanctum::actingAs($user->fresh());
        $r = $this->getJson('/api/v1/mi/asistencia')->assertOk();

        $r->assertJsonCount(1, 'alumnos')
            ->assertJsonPath('alumnos.0.alumno_id', $alumno->id)
            ->assertJsonPath('alumnos.0.resumen.clases', 4)
            ->assertJsonPath('alumnos.0.resumen.presentes', 1)
            ->assertJsonPath('alumnos.0.resumen.tardes', 1)
            ->assertJsonPath('alumnos.0.resumen.porcentaje', 50)
            ->assertJsonPath('alumnos.0.bloques.0.nombre', 'Martes')
            ->assertJsonPath('alumnos.0.bloques.0.horarios.0.dia_nombre', 'Martes')
            ->assertJsonPath('alumnos.0.bloques.0.horarios.0.inicio', '19:00')
            ->assertJsonPath('alumnos.0.bloques.0.meses.0.mes', 4)
            ->assertJsonPath('alumnos.0.bloques.0.meses.1.mes', 3)
            ->assertJsonPath('alumnos.0.bloques.0.meses.1.porcentaje', 67)
            ->assertJsonPath('alumnos.0.ultimas.0.tipo', 'ausencia_justificada');
    }

    public function test_sin_ficha_de_alumno_devuelve_lista_vacia(): void
    {
        Sanctum::actingAs($this->usuario('Sin ficha'));
        $this->getJson('/api/v1/mi/asistencia')->assertOk()->assertJsonCount(0, 'alumnos');
    }

    public function test_el_docente_borra_una_marca_y_la_planilla_trae_su_id(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'));
        $alumno = $this->inscribirAlumno($this->usuario('Alumna')->persona, $bloque);
        $coord = $this->usuario('Coord');
        $this->rolDocenteEnSede($coord->persona, $bloque->sede, 'coordinador');
        $a = Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => now()->toDateString(), 'tipo_asistencia' => 'presente']);

        Sanctum::actingAs($coord->fresh());
        $this->getJson("/api/v1/bloques/{$bloque->id}/asistencia")->assertOk()->assertJsonPath('alumnos.0.asistencia_id', $a->id);
        $this->deleteJson("/api/v1/asistencias/{$a->id}")->assertOk();
        $this->assertDatabaseMissing('asistencias', ['id' => $a->id]);
    }

    public function test_el_alumno_no_puede_borrar_marcas(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'));
        $user = $this->usuario('Alumna');
        $alumno = $this->inscribirAlumno($user->persona, $bloque);
        $a = Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => now()->toDateString(), 'tipo_asistencia' => 'ausencia_injustificada']);

        Sanctum::actingAs($user->fresh());
        $this->deleteJson("/api/v1/asistencias/{$a->id}")->assertForbidden();
        $this->assertDatabaseHas('asistencias', ['id' => $a->id]);
    }

    public function test_admin_cierra_una_o_todas_las_sesiones_de_otra_cuenta(): void
    {
        $admin = $this->admin();
        $ana = $this->usuario('Ana');
        $celu = $ana->createToken('android app');
        $ana->createToken('ios app');
        Dispositivo::query()->create(['user_id' => $ana->id, 'token' => 'ExponentPushToken[x]', 'plataforma' => 'android']);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/usuarios/{$ana->id}/sesiones/{$celu->accessToken->id}")->assertOk()->assertJsonPath('cerradas', 1);
        $this->assertSame(1, $ana->tokens()->count());

        $this->deleteJson("/api/v1/usuarios/{$ana->id}/sesiones")->assertOk()->assertJsonPath('cerradas', 1);
        $this->assertSame(0, $ana->tokens()->count());
        $this->assertSame(0, Dispositivo::query()->where('user_id', $ana->id)->count());
        $this->assertDatabaseHas('auditoria', ['accion' => 'usuario.sesiones_cerradas']);
    }

    public function test_sin_permiso_no_cierra_sesiones_ajenas(): void
    {
        $ana = $this->usuario('Ana');
        $ana->createToken('android app');
        Sanctum::actingAs($this->usuario('Beto'));
        $this->deleteJson("/api/v1/usuarios/{$ana->id}/sesiones")->assertForbidden();
        $this->assertSame(1, $ana->tokens()->count());
    }

    public function test_los_avisos_vienen_paginados(): void
    {
        $user = $this->usuario('Ana');
        for ($i = 0; $i < 35; $i++) {
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(), 'type' => 'aviso', 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
                'data' => ['titulo' => "Aviso $i", 'mensaje' => 'x'], 'created_at' => now()->subMinutes($i),
            ]);
        }
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notificaciones')->assertOk()
            ->assertJsonCount(30, 'data')->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.total', 35)
            ->assertJsonPath('no_leidas', 35)->assertJsonPath('data.0.titulo', 'Aviso 0');
        $this->getJson('/api/v1/notificaciones?page=2')->assertOk()->assertJsonCount(5, 'data');
    }
}
