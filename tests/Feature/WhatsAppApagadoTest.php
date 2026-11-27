<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Mockery;
use Tests\TestCase;
use Twilio\Rest\Client;

class WhatsAppApagadoTest extends TestCase
{
    public function test_apagado_no_envia_nada_aunque_haya_cliente(): void
    {
        config(['services.whatsapp.avisos_habilitados' => false]);

        $client = Mockery::mock(Client::class);
        $client->messages = Mockery::mock();
        $client->messages->shouldNotReceive('create');

        $servicio = new WhatsAppService;
        $servicio->setClient($client);

        $resultado = $servicio->send('hola', '+5491112345678');
        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('apagado', $resultado['error']);
    }

    public function test_la_difusion_no_ofrece_whatsapp_cuando_esta_apagado(): void
    {
        config(['services.whatsapp.avisos_habilitados' => false]);

        $canales = \App\Domain\Notificaciones\NotificacionService::canalesOfrecidos();
        $this->assertNotContains('whatsapp', $canales);
        $this->assertContains('push', $canales);
    }
}
