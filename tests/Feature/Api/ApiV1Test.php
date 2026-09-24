<?php

namespace Tests\Feature\Api;

use App\Models\Asistencia;
use App\Models\Beca;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\InventarioItem;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_login_emite_token_con_vencimiento_y_rechaza_credenciales_invalidas(): void
    {
        $user = $this->usuario('Ana');

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'mala'])
            ->assertStatus(422)->assertJsonValidationErrors('username');

        $r = $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password', 'dispositivo' => 'Pixel'])
            ->assertOk()->assertJsonStructure(['token', 'tipo', 'expira']);
        $this->assertNotNull($r->json('expira'));

        $this->withToken($r->json('token'))->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('usuario.username', $user->username);
    }

    public function test_refresh_rota_el_token_y_logout_lo_revoca(): void
    {
        $user = $this->usuario('Ana');
        $viejo = $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])->json('token');

        $nuevo = $this->withToken($viejo)->postJson('/api/v1/auth/refresh')->assertOk()->json('token');
        $this->assertNotSame($viejo, $nuevo);
        $this->assertSame(1, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($nuevo)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_cuenta_desactivada_no_ingresa_ni_usa_su_token(): void
    {
        $user = $this->usuario('Ana');
        $token = $user->createToken('app')->plainTextToken;
        $user->forceFill(['activo' => false])->save();

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_sin_token_es_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/inicio')->assertUnauthorized();
    }

    public function test_me_devuelve_funciones_contextos_y_modulos_segun_permisos(): void
    {
        $palomar = $this->sede('Palomar');
        $quilmes = $this->sede('Quilmes');
        $user = $this->usuario('Juan');
        $this->asignarDocente($user->persona, $this->bloque($palomar));
        $this->asignarRol($user->persona, 'contador');
        $this->rolDocenteEnSede($user->persona, $quilmes, 'coordinador');
        Sanctum::actingAs($user->fresh());

        $r = $this->getJson('/api/v1/me')->assertOk();
        $modulos = collect($r->json('modulos'))->pluck('clave')->all();
        $contextos = collect($r->json('contextos'))->pluck('etiqueta')->all();

        $this->assertContains('asistencia', $modulos);
        $this->assertContains('pagos', $modulos);
        $this->assertNotContains('usuarios', $modulos);
        $this->assertContains('Profesor — Palomar', $contextos);
        $this->assertContains('Contador — Global', $contextos);
        $this->assertContains('Coordinador — Quilmes', $contextos);
        $this->assertContains('pagos.view', $r->json('permisos'));
        $this->assertNotContains('pagos.create', $r->json('permisos'));
    }

    public function test_inicio_muestra_tarjetas_por_funcion_y_prioriza_contexto(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $bloque->horarios()->create(['dia_semana' => now()->dayOfWeekIso, 'hora_inicio' => '18:00:00', 'hora_fin' => '19:30:00']);
        $user = $this->usuario('Profe');
        $this->asignarDocente($user->persona, $bloque);
        $this->inscribirAlumno($user->persona, $this->bloque($sede, 'Otro'));
        Sanctum::actingAs($user->fresh());

        $tipos = collect($this->getJson('/api/v1/inicio')->assertOk()->json('tarjetas'))->pluck('tipo')->all();
        $this->assertContains('clases_hoy', $tipos);
        $this->assertContains('mi_espacio', $tipos);
        $this->assertNotContains('finanzas', $tipos);

        $primera = $this->withHeader('X-Contexto', 'profesor:'.$sede->id)->getJson('/api/v1/inicio')->json('tarjetas.0.tipo');
        $this->assertSame('clases_hoy', $primera);
    }

    public function test_no_se_accede_a_recursos_ajenos_cambiando_el_id(): void
    {
        $sede = $this->sede('Palomar');
        $mio = $this->bloque($sede, 'Mío');
        $ajeno = $this->bloque($this->sede('Varela'), 'Ajeno');
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $mio);
        $alumnoAjeno = $this->inscribirAlumno($this->persona('Ajena'), $ajeno);
        $item = InventarioItem::query()->create(['sede_id' => $ajeno->sede_id, 'tipo' => 'instrumento', 'nombre' => 'Repique', 'cantidad' => 1, 'propietario_tipo' => 'escuela', 'estado' => 'bueno']);
        Sanctum::actingAs($profe->fresh());

        $this->getJson("/api/v1/alumnos/{$alumnoAjeno->id}")->assertForbidden();
        $this->getJson("/api/v1/alumnos/{$alumnoAjeno->id}/estado-cuenta")->assertForbidden();
        $this->getJson("/api/v1/bloques/{$ajeno->id}/asistencia")->assertForbidden();
        $this->postJson("/api/v1/bloques/{$ajeno->id}/asistencia", ['fecha' => now()->toDateString(), 'registros' => [['alumno_id' => $alumnoAjeno->id, 'tipo' => 'presente']]])->assertForbidden();
        $this->getJson("/api/v1/inventario/{$item->id}")->assertForbidden();
        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->assertSame([], collect($this->getJson('/api/v1/alumnos')->assertOk()->json('data'))->where('id', $alumnoAjeno->id)->all());
    }

    public function test_tomar_asistencia_es_idempotente_y_valida_pertenencia(): void
    {
        $bloque = $this->bloque($this->sede('Banfield'));
        $a1 = $this->inscribirAlumno($this->persona('Uno'), $bloque);
        $a2 = $this->inscribirAlumno($this->persona('Dos'), $bloque);
        $intruso = $this->inscribirAlumno($this->persona('Intruso'), $this->bloque($this->sede('Otra')));
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        Sanctum::actingAs($profe->fresh());
        $uuid = (string) Str::uuid();
        $payload = ['fecha' => now()->toDateString(), 'client_uuid' => $uuid, 'registros' => [
            ['alumno_id' => $a1->id, 'tipo' => 'presente'],
            ['alumno_id' => $a2->id, 'tipo' => 'ausencia_injustificada'],
        ]];

        $this->postJson("/api/v1/bloques/{$bloque->id}/asistencia", $payload)->assertOk()->assertJsonPath('guardadas', 2)->assertJsonPath('duplicado', false);
        $this->postJson("/api/v1/bloques/{$bloque->id}/asistencia", $payload)->assertOk()->assertJsonPath('duplicado', true);
        $this->assertSame(2, Asistencia::query()->count());
        $this->assertSame($profe->id, (int) Asistencia::query()->where('alumno_id', $a1->id)->value('registrado_por'));

        $planilla = $this->getJson("/api/v1/bloques/{$bloque->id}/asistencia?fecha=".now()->toDateString())->assertOk();
        $this->assertTrue($planilla->json('tomada'));
        $this->assertSame('presente', collect($planilla->json('alumnos'))->firstWhere('alumno_id', $a1->id)['tipo']);

        $this->postJson("/api/v1/bloques/{$bloque->id}/asistencia", ['fecha' => now()->toDateString(), 'registros' => [['alumno_id' => $intruso->id, 'tipo' => 'presente']]])
            ->assertStatus(422);
    }

    public function test_sincronizacion_offline_no_pisa_una_correccion_posterior(): void
    {
        $bloque = $this->bloque($this->sede('Banfield'));
        $alumno = $this->inscribirAlumno($this->persona('Uno'), $bloque);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $coord = $this->usuario('Coord');
        $this->rolDocenteEnSede($coord->persona, $bloque->sede, 'coordinador');
        $capturado = now()->subHour();

        Sanctum::actingAs($coord->fresh());
        $this->postJson("/api/v1/bloques/{$bloque->id}/asistencia", ['fecha' => now()->toDateString(), 'registros' => [['alumno_id' => $alumno->id, 'tipo' => 'ausencia_justificada']]])->assertOk();

        Sanctum::actingAs($profe->fresh());
        $r = $this->postJson("/api/v1/bloques/{$bloque->id}/asistencia", [
            'fecha' => now()->toDateString(), 'capturado_en' => $capturado->toIso8601String(),
            'registros' => [['alumno_id' => $alumno->id, 'tipo' => 'ausencia_injustificada']],
        ])->assertOk();

        $this->assertSame([$alumno->id], $r->json('conflictos'));
        $this->assertSame('ausencia_justificada', Asistencia::query()->value('tipo_asistencia'));
    }

    public function test_alumno_consulta_su_estado_de_cuenta_con_beca(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'));
        $user = $this->usuario('Alumna');
        $alumno = $this->inscribirAlumno($user->persona, $bloque);
        Cuota::query()->create(['nombre' => 'Enero', 'año' => (int) now()->year, 'mes' => 1, 'monto' => 20000, 'alcance' => 'general', 'activo' => true]);
        Beca::query()->create(['alumno_id' => $alumno->id, 'tipo' => 'monto_fijo', 'monto' => 5000, 'fecha_inicio' => now()->startOfYear(), 'estado' => 'activa']);
        Sanctum::actingAs($user->fresh());

        $r = $this->getJson('/api/v1/mi/estado-cuenta')->assertOk();
        $this->assertSame(15000, (int) $r->json('cuentas.0.items.0.neto'));
        $this->assertSame(15000, (int) $r->json('cuentas.0.totales.saldo'));
        $this->getJson("/api/v1/alumnos/{$alumno->id}/estado-cuenta")->assertOk();
    }

    public function test_inventario_por_codigo_qr_y_movimiento(): void
    {
        $varela = $this->sede('Varela');
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $varela);
        Sanctum::actingAs($encargado->fresh());

        $creado = $this->postJson('/api/v1/inventario', [
            'sede_id' => $varela->id, 'tipo' => 'instrumento', 'nombre' => 'Surdo 22', 'cantidad' => 1,
            'propietario_tipo' => 'escuela', 'estado' => 'bueno',
        ])->assertCreated()->json('data');
        $codigo = $creado['codigo'];
        $this->assertNotEmpty($codigo);

        $this->getJson('/api/v1/inventario/codigo/'.urlencode('https://chilinga.test/tambor/'.$codigo))->assertOk()->assertJsonPath('data.id', $creado['id']);
        $this->postJson("/api/v1/inventario/{$creado['id']}/movimientos", ['tipo' => 'reparacion', 'estado' => 'reparacion', 'nota' => 'Parche roto'])
            ->assertOk()->assertJsonPath('data.estado', 'reparacion');

        $this->postJson('/api/v1/inventario', [
            'sede_id' => $this->sede('Palomar')->id, 'tipo' => 'instrumento', 'nombre' => 'Fuera', 'cantidad' => 1,
            'propietario_tipo' => 'escuela', 'estado' => 'bueno',
        ])->assertForbidden();
    }

    public function test_contador_consulta_pagos_y_no_anula_sin_permiso(): void
    {
        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        $alumno = $this->inscribirAlumno($this->persona('A'), $this->bloque($this->sede('Banfield')));
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);
        Sanctum::actingAs($contador->fresh());

        $this->getJson('/api/v1/pagos')->assertOk()->assertJsonPath('data.0.id', $pago->id);
        $this->getJson('/api/v1/cuotas?anio=2026')->assertOk()->assertJsonPath('data.0.pagos', 1);
        $this->postJson("/api/v1/pagos/{$pago->id}/anular", ['motivo' => 'Prueba de permisos'])->assertForbidden();
    }

    public function test_calendario_une_clases_eventos_y_shows_del_alcance(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $bloque->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']);
        $otra = $this->sede('Varela');
        Evento::query()->create(['titulo' => 'Muestra Palomar', 'fecha' => now()->addDay(), 'sede_id' => $sede->id, 'tipo_evento' => 'muestra']);
        Evento::query()->create(['titulo' => 'Reunión Varela', 'fecha' => now()->addDay(), 'sede_id' => $otra->id, 'tipo_evento' => 'otro']);
        Evento::query()->create(['titulo' => 'Aniversario', 'fecha' => now()->addDay(), 'tipo_evento' => 'aniversario']);
        $user = $this->usuario('Alumna');
        $this->inscribirAlumno($user->persona, $bloque);
        Sanctum::actingAs($user->fresh());

        $items = collect($this->getJson('/api/v1/calendario?desde='.now()->toDateString().'&hasta='.now()->addDays(8)->toDateString())->assertOk()->json('items'));
        $this->assertTrue($items->contains('tipo', 'clase'));
        // La hora sale como HH:MM (el cast datetime no debe filtrar la fecha).
        $this->assertSame('18:00', $items->firstWhere('tipo', 'clase')['inicio']);
        $this->assertTrue($items->contains('titulo', 'Muestra Palomar'));
        $this->assertTrue($items->contains('titulo', 'Aniversario'));
        $this->assertFalse($items->contains('titulo', 'Reunión Varela'));
    }

    public function test_admin_asigna_rol_con_alcance_por_api(): void
    {
        $admin = $this->admin();
        $otro = $this->usuario('Otro');
        $sede = $this->sede('Quilmes');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/usuarios/{$otro->id}/asignaciones", ['tipo' => 'rol', 'nombre' => 'tesorero', 'ambito' => 'sede', 'sede_id' => $sede->id])
            ->assertCreated();
        $this->assertTrue($otro->fresh()->acceso()->puedeEnSede('pagos.create', $sede->id));
        $this->getJson('/api/v1/accesos/catalogo')->assertOk()->assertJsonFragment(['clave' => 'contador']);
    }
}
