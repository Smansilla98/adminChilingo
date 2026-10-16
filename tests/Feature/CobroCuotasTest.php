<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Pago de cuotas desde el sistema: un alumno en varios bloques de sedes distintas paga
 * todo junto, por transferencia o por Mercado Pago, y el pago se reparte por cuota con
 * lo que le toca a cada docente.
 */
class CobroCuotasTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private User $juan;

    private Alumno $alumno;

    /** @var array<int, Cuota> */
    private array $cuotas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
        config([
            'chilinga.cobro' => ['alias' => 'cifra.jardin.pez', 'titular' => 'La Chilinga'],
            'services.mercadopago' => ['access_token' => null, 'webhook_secret' => null],
        ]);

        $persona = $this->persona('Juan Pérez');
        $this->juan = $this->usuario('Juan Pérez', $persona);
        foreach ([['Palomar', 40, 18000], ['Saavedra', 50, 20000], ['Banfield', 40, 15000], ['Varela', 30, 16000]] as [$nombre, $porc, $monto]) {
            $sede = $this->sede($nombre);
            $sede->update(['liquidacion_porc_docente' => $porc]);
            $bloque = $this->bloque($sede, "Bloque {$nombre}");
            $this->asignarDocente($this->persona("Profe {$nombre}"), $bloque);
            $this->alumno = $this->inscribirAlumno($persona, $bloque);
            $this->cuotas[] = Cuota::query()->create([
                'bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => "Cuota {$nombre}",
                'año' => now()->year, 'mes' => now()->month, 'monto' => $monto, 'activo' => true,
            ]);
        }
    }

    public function test_lista_las_cuotas_de_todos_los_bloques_y_sedes_con_su_docente(): void
    {
        $this->actingAs($this->juan)->get('/mi-cuota/pagar')
            ->assertOk()
            ->assertSee('Cuota Palomar')->assertSee('Cuota Varela')
            ->assertSee('Profe: Profe Saavedra')
            ->assertSee('cifra.jardin.pez');
    }

    public function test_transferencia_de_cuatro_cuotas_en_cuatro_sedes_se_reparte_por_docente(): void
    {
        $ids = array_map(fn (Cuota $c) => $c->id, $this->cuotas);
        $respuesta = $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => $ids, 'medio' => 'transferencia']);
        $pago = ComprobanteCuotaAlumno::query()->firstOrFail();
        $respuesta->assertRedirect(route('alumno.cuota.pago', $pago->referencia));
        $this->assertSame('esperando_pago', $pago->estado);
        $this->assertEquals(69000, (float) $pago->monto_total);
        $this->assertCount(4, $pago->items);

        $this->actingAs($this->juan)->get(route('alumno.cuota.pago', $pago->referencia))
            ->assertOk()->assertSee('cifra.jardin.pez')->assertSee($pago->referencia);

        // No se puede iniciar otro pago por las mismas cuotas mientras este siga en curso.
        $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => [$ids[0]], 'medio' => 'transferencia'])
            ->assertSessionHasErrors('cuota_ids');

        $this->actingAs($this->juan)->post(route('alumno.cuota.pago.comprobante', $pago->referencia), [
            'fecha_pago' => now()->toDateString(),
            'comprobante' => UploadedFile::fake()->image('transferencia.jpg'),
        ])->assertRedirect();
        $this->assertSame('pendiente', $pago->fresh()->estado);

        $this->actingAs($this->admin())->post(route('comprobantes-cuota-alumnos.aprobar-pago', $pago->id))->assertRedirect();

        $detalles = PagoDetalle::query()->where('alumno_id', $this->alumno->id)->orderBy('cuota_id')->get();
        $this->assertCount(4, $detalles);
        // Docente según el porcentaje de cada sede: 40 % de 18000, 50 % de 20000, 40 % de 15000, 30 % de 16000.
        $this->assertSame([7200.0, 10000.0, 6000.0, 4800.0], $detalles->map(fn ($d) => (float) $d->abono_profesor)->all());
        $this->actingAs($this->juan)->get('/mi-cuota/pagar')->assertSee('No hay cuotas para pagar desde acá')->assertSee('4 pagas');
    }

    public function test_mercado_pago_registra_el_pago_solo_y_una_sola_vez(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-1']),
            'api.mercadopago.com/v1/payments/*' => function () {
                $ref = ComprobanteCuotaAlumno::query()->value('referencia');

                return Http::response(['id' => 555, 'status' => 'approved', 'external_reference' => $ref, 'transaction_amount' => 38000, 'currency_id' => 'ARS', 'date_approved' => now()->toIso8601String()]);
            },
        ]);

        $ids = [$this->cuotas[0]->id, $this->cuotas[1]->id];
        $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => $ids, 'medio' => 'mercadopago'])
            ->assertRedirect('https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-1');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'checkout/preferences') && $r['external_reference'] === ComprobanteCuotaAlumno::query()->value('referencia') && count($r['items']) === 2);

        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '555']])->assertOk();
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '555']])->assertOk();

        $pago = ComprobanteCuotaAlumno::query()->firstOrFail();
        $this->assertTrue($pago->estaPagado());
        $this->assertSame('555', $pago->mp_payment_id);
        $this->assertSame(2, PagoDetalle::query()->where('alumno_id', $this->alumno->id)->count());
    }

    public function test_mercado_pago_con_importe_menor_queda_para_revisar(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref-2', 'init_point' => 'https://mp.test/pagar']),
            'api.mercadopago.com/v1/payments/*' => fn () => Http::response(['status' => 'approved', 'external_reference' => ComprobanteCuotaAlumno::query()->value('referencia'), 'transaction_amount' => 100, 'currency_id' => 'ARS']),
        ]);
        $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => [$this->cuotas[0]->id], 'medio' => 'mercadopago']);
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '777']])->assertOk();

        $pago = ComprobanteCuotaAlumno::query()->firstOrFail();
        $this->assertFalse($pago->estaPagado());
        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame(0, PagoDetalle::query()->count());
    }

    public function test_aviso_con_firma_invalida_se_rechaza(): void
    {
        config(['services.mercadopago' => ['access_token' => 'TEST-token', 'webhook_secret' => 'secreto']]);
        Http::fake();
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '1']], ['x-signature' => 'ts=1,v1=basura', 'x-request-id' => 'r'])
            ->assertStatus(401);
        Http::assertNothingSent();
    }

    public function test_aviso_con_firma_valida_se_procesa(): void
    {
        config(['services.mercadopago' => ['access_token' => 'TEST-token', 'webhook_secret' => 'secreto']]);
        Http::fake(['api.mercadopago.com/v1/payments/*' => Http::response(['status' => 'pending', 'external_reference' => 'CH-NADA'])]);
        $firma = hash_hmac('sha256', 'id:42;request-id:req-1;ts:1700000000;', 'secreto');
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '42']], ['x-signature' => "ts=1700000000,v1={$firma}", 'x-request-id' => 'req-1'])
            ->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/payments/42'));
    }

    public function test_otro_alumno_no_ve_ni_paga_un_pago_ajeno(): void
    {
        $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => [$this->cuotas[0]->id], 'medio' => 'transferencia']);
        $pago = ComprobanteCuotaAlumno::query()->firstOrFail();

        $otra = $this->persona('Ana');
        $this->inscribirAlumno($otra, $this->cuotas[0]->bloque);
        $ana = $this->usuario('Ana', $otra);
        $this->actingAs($ana)->get(route('alumno.cuota.pago', $pago->referencia))->assertNotFound();
        $this->actingAs($ana)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => [$this->cuotas[1]->id], 'medio' => 'transferencia'])->assertForbidden();
    }

    public function test_admin_sin_ficha_ve_la_explicacion_y_la_ficha_que_podria_ser_suya(): void
    {
        $admin = $this->admin('Juan Pérez');
        // La inscripción quedó cargada como otra persona con el mismo nombre.
        $this->actingAs($admin)->get('/mi-cuota/pagar')
            ->assertOk()
            ->assertSee('Tu usuario no tiene una ficha de alumno vinculada')
            ->assertSee('Bloque Palomar')
            ->assertSee('Fusionar');
        $this->actingAs($admin)->get('/mi-cuota')->assertOk()->assertSee('Tu usuario no tiene una ficha de alumno vinculada');
    }

    public function test_alumno_sin_ficha_no_ve_fichas_ajenas(): void
    {
        $this->actingAs($this->usuario('Juan Pérez'))->get('/mi-cuota/pagar')
            ->assertOk()
            ->assertSee('Pedile a administración')
            ->assertDontSee('Bloque Palomar');
    }

    public function test_sin_cuotas_emitidas_lo_dice(): void
    {
        Cuota::query()->delete();
        $this->actingAs($this->juan)->get('/mi-cuota/pagar')->assertOk()->assertSee('No encontramos cuotas emitidas para tus bloques');
    }

    public function test_con_dos_fichas_abre_la_que_tiene_cuotas_para_pagar(): void
    {
        // Ficha duplicada sin bloques, creada antes que la real.
        $vacia = Alumno::query()->create(['persona_id' => $this->juan->persona_id, 'nombre_apellido' => 'Juan Pérez (duplicada)', 'activo' => true]);
        Alumno::query()->whereKey($vacia->id)->update(['created_at' => now()->subYear()]);

        $this->actingAs($this->juan)->get('/mi-cuota/pagar')->assertOk()->assertSee('Cuota Palomar');
        $this->actingAs($this->juan)->get('/mi-cuota/pagar?alumno='.$vacia->id)->assertOk()->assertSee('No encontramos cuotas emitidas');
    }

    public function test_cancelar_libera_las_cuotas(): void
    {
        $this->actingAs($this->juan)->post('/mi-cuota/pagar', ['alumno_id' => $this->alumno->id, 'cuota_ids' => [$this->cuotas[0]->id], 'medio' => 'transferencia']);
        $pago = ComprobanteCuotaAlumno::query()->firstOrFail();
        $this->actingAs($this->juan)->post(route('alumno.cuota.pago.cancelar', $pago->referencia))->assertRedirect(route('alumno.cuota.pagar'));
        $this->assertSame(0, ComprobanteCuotaAlumno::query()->count());
    }
}
