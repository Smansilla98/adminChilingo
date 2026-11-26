<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** WhatsApp Cloud API (Meta): plantillas, errores que se reintentan y los que no, y que el texto libre se rechaza. */
class WhatsAppCloudTest extends TestCase
{
    use RefreshDatabase;

    private function cloud(): void
    {
        config([
            'services.whatsapp.driver' => 'cloud',
            'services.whatsapp.cloud.token' => 'token-de-prueba',
            'services.whatsapp.cloud.phone_number_id' => '123456789',
            'services.whatsapp.cloud.api_version' => 'v21.0',
        ]);
    }

    public function test_envia_una_plantilla_y_registra_el_id_de_mensaje(): void
    {
        $this->cloud();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);

        $r = app(WhatsAppService::class)->sendTemplate('+5491112345678', 'recordatorio_cuota', 'es_AR', ['Ana', '10000']);

        $this->assertTrue($r['success']);
        $this->assertSame('wamid.ABC', $r['sid']);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v21.0/123456789/messages')
            && $req['messaging_product'] === 'whatsapp'
            && $req['to'] === '5491112345678'
            && $req['type'] === 'template'
            && $req['template']['name'] === 'recordatorio_cuota'
            && $req['template']['components'][0]['parameters'][1]['text'] === '10000');
        $this->assertDatabaseHas('whatsapp_mensajes', ['twilio_sid' => 'wamid.ABC', 'telefono' => '+5491112345678']);
    }

    public function test_un_rechazo_de_datos_no_se_reintenta(): void
    {
        $this->cloud();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Plantilla no aprobada', 'code' => 132001]], 400)]);

        $r = app(WhatsAppService::class)->sendTemplate('+5491112345678', 'sin_aprobar', 'es_AR');

        $this->assertFalse($r['success']);
        $this->assertSame(132001, $r['codigo']);
        $this->assertFalse($r['reintentable']);
    }

    public function test_un_error_del_servidor_de_meta_si_se_reintenta(): void
    {
        $this->cloud();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Servicio no disponible']], 503)]);

        $r = app(WhatsAppService::class)->sendTemplate('+5491112345678', 'recordatorio_cuota', 'es_AR');

        $this->assertFalse($r['success']);
        $this->assertTrue($r['reintentable']);
    }

    public function test_el_texto_libre_se_rechaza_con_driver_cloud(): void
    {
        $this->cloud();
        Http::fake();

        $r = app(WhatsAppService::class)->send('hola', '+5491112345678');

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('plantilla aprobada', $r['error']);
        Http::assertNothingSent();
    }

    public function test_un_numero_invalido_no_llega_a_la_api(): void
    {
        $this->cloud();
        Http::fake();

        $r = app(WhatsAppService::class)->sendTemplate('abc', 'recordatorio_cuota', 'es_AR');

        $this->assertFalse($r['success']);
        Http::assertNothingSent();
    }

    public function test_sin_credenciales_no_esta_configurado(): void
    {
        config(['services.whatsapp.driver' => 'cloud', 'services.whatsapp.cloud.token' => null, 'services.whatsapp.cloud.phone_number_id' => null]);

        $this->assertFalse(app(WhatsAppService::class)->isConfigured());
    }

    public function test_el_driver_por_defecto_sigue_siendo_twilio(): void
    {
        config(['services.whatsapp.driver' => null]);

        $this->assertSame('twilio', app(WhatsAppService::class)->driver());
    }
}
