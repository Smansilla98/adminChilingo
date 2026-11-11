<?php

namespace Tests\Feature;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Token push: pasa a otra cuenta solo desde una sesión de la app y se borra al salir. */
class DispositivosPushTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function sesion(User $user): string
    {
        return $user->createToken('android app')->plainTextToken;
    }

    public function test_cambiar_de_cuenta_en_el_celular_pasa_el_token_y_lo_borra_de_la_anterior(): void
    {
        $ana = $this->usuario('Ana');
        $beto = $this->usuario('Beto');
        $push = 'ExponentPushToken[cel-1]';

        $this->withToken($this->sesion($ana))->postJson('/api/v1/dispositivos', ['token' => $push])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->sesion($beto))->postJson('/api/v1/dispositivos', ['token' => $push])->assertOk();

        $this->assertSame([$beto->id], Dispositivo::query()->where('token', $push)->pluck('user_id')->all());
        $this->assertSame(0, $ana->dispositivos()->count());
    }

    public function test_sin_sesion_de_la_app_no_se_roba_el_token_de_otro(): void
    {
        $ana = $this->usuario('Ana');
        Dispositivo::query()->create(['user_id' => $ana->id, 'token' => 'ExponentPushToken[cel-2]']);

        $this->actingAs($this->usuario('Intruso'))->postJson('/api/v1/dispositivos', ['token' => 'ExponentPushToken[cel-2]'])->assertStatus(409);
        $this->assertSame($ana->id, (int) Dispositivo::query()->where('token', 'ExponentPushToken[cel-2]')->value('user_id'));
    }

    public function test_al_salir_se_borra_el_celular_de_esa_sesion(): void
    {
        $ana = $this->usuario('Ana');
        $token = $this->sesion($ana);
        $this->withToken($token)->postJson('/api/v1/dispositivos', ['token' => 'ExponentPushToken[cel-3]'])->assertOk();
        $this->assertNotNull(Dispositivo::query()->where('token', 'ExponentPushToken[cel-3]')->value('personal_access_token_id'));

        // La sesión rota y el celular sigue asociado.
        $nuevo = $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($nuevo)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, Dispositivo::query()->count());
        $this->assertSame(0, $ana->tokens()->count());
    }
}
