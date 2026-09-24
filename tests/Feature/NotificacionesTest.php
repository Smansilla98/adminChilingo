<?php

namespace Tests\Feature;

use App\Domain\Notificaciones\Avisos;
use App\Domain\Notificaciones\NotificacionService;
use App\Models\Cuota;
use App\Models\Dispositivo;
use App\Models\Evento;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_registrar_un_pago_avisa_una_sola_vez_al_alumno(): void
    {
        $user = $this->usuario('Alumna');
        $alumno = $this->inscribirAlumno($user->persona, $this->bloque($this->sede('Banfield')));
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $detalle = $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);

        $this->assertSame(1, $user->fresh()->notifications()->count());
        app(Avisos::class)->pagoRegistrado($detalle); // reintento: no duplica
        $this->assertSame(1, $user->fresh()->notifications()->count());

        Sanctum::actingAs($user->fresh());
        $r = $this->getJson('/api/v1/notificaciones')->assertOk()->assertJsonPath('no_leidas', 1);
        $this->postJson('/api/v1/notificaciones/'.$r->json('data.0.id').'/leer')->assertOk();
        $this->getJson('/api/v1/notificaciones')->assertJsonPath('no_leidas', 0);
    }

    public function test_avisos_de_cuota_vencida_son_idempotentes(): void
    {
        $user = $this->usuario('Deudor');
        $this->inscribirAlumno($user->persona, $this->bloque($this->sede('Palomar')));
        Cuota::query()->create(['nombre' => 'Agosto', 'año' => (int) now()->year, 'mes' => 8, 'monto' => 1000, 'alcance' => 'general', 'activo' => true, 'fecha_vencimiento' => now()->subDays(3)]);

        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-vencidas'])->expectsOutput('Avisos nuevos: 1')->assertSuccessful();
        $this->artisan('chilinga:avisos', ['tipo' => 'cuotas-vencidas'])->expectsOutput('Avisos nuevos: 0')->assertSuccessful();
        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_eventos_de_manana_avisan_solo_a_su_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $dePalomar = $this->usuario('Palomar');
        $this->inscribirAlumno($dePalomar->persona, $this->bloque($palomar));
        $deVarela = $this->usuario('Varela');
        $this->inscribirAlumno($deVarela->persona, $this->bloque($varela));
        Evento::query()->create(['titulo' => 'Muestra', 'fecha' => now()->addDay(), 'sede_id' => $palomar->id, 'tipo_evento' => 'muestra']);

        app(Avisos::class)->eventosProximos();

        $this->assertSame(1, $dePalomar->fresh()->notifications()->count());
        $this->assertSame(0, $deVarela->fresh()->notifications()->count());
    }

    public function test_push_desactivado_no_llama_a_servicios_externos(): void
    {
        Http::fake();
        $user = $this->usuario('Ana');
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]']);

        $r = app(NotificacionService::class)->enviar($user, 'prueba:1', ['tipo' => 'aviso', 'titulo' => 'Hola', 'mensaje' => 'Test']);

        $this->assertSame(['interna' => 'enviado', 'push' => 'omitido'], $r);
        Http::assertNothingSent();
    }

    public function test_push_habilitado_envia_a_expo_y_limpia_tokens_dados_de_baja(): void
    {
        config(['services.expo.push_habilitado' => true]);
        Http::fake(['exp.host/*' => Http::response(['data' => [
            ['status' => 'ok', 'id' => 'x'],
            ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);
        $user = $this->usuario('Ana');
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[uno]']);
        Dispositivo::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[viejo]']);

        $r = app(NotificacionService::class)->enviar($user, 'prueba:2', ['tipo' => 'aviso', 'titulo' => 'Hola', 'mensaje' => 'Test'], ['push']);

        $this->assertSame(['push' => 'enviado'], $r);
        Http::assertSentCount(1);
        $this->assertDatabaseMissing('dispositivos', ['token' => 'ExponentPushToken[viejo]']);
    }

    public function test_registrar_dispositivo_valida_token_de_expo(): void
    {
        Sanctum::actingAs($this->usuario('Ana'));
        $this->postJson('/api/v1/dispositivos', ['token' => 'cualquier-cosa'])->assertStatus(422);
        $this->postJson('/api/v1/dispositivos', ['token' => 'ExponentPushToken[xyz]', 'plataforma' => 'android'])->assertOk();
        $this->assertDatabaseHas('dispositivos', ['token' => 'ExponentPushToken[xyz]']);
    }
}
