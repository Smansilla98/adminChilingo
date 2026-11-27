<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Twilio\Rest\Client;

class WhatsAppContentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.whatsapp_from' => '+14155238886',
            'services.twilio.status_callback_url' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_envia_plantilla_con_content_sid_y_variables(): void
    {
        $messages = Mockery::mock();
        $messages->shouldReceive('create')
            ->once()
            ->withArgs(function (string $to, array $payload) {
                return $to === 'whatsapp:+5491112345678'
                    && $payload['from'] === 'whatsapp:+14155238886'
                    && $payload['contentSid'] === 'HXtest123'
                    && $payload['contentVariables'] === '{"1":"Hola"}'
                    && ! array_key_exists('body', $payload);
            })
            ->andReturn((object) ['sid' => 'SMplantilla', 'status' => 'queued']);
        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        $service = new WhatsAppService;
        $service->setClient($client);
        $result = $service->sendContentTemplate('91112345678', 'HXtest123', ['1' => 'Hola']);

        $this->assertTrue($result['success']);
        $this->assertSame('SMplantilla', $result['sid']);
    }

    public function test_plantilla_rechazada_devuelve_error_sin_lanzar(): void
    {
        $messages = Mockery::mock();
        $messages->shouldReceive('create')
            ->once()
            ->andThrow(new \Twilio\Exceptions\RestException('ContentSid Required', 21618, 400));
        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        $service = new WhatsAppService;
        $service->setClient($client);
        $result = $service->sendContentTemplate('91112345678', 'HXtest123', ['1' => 'Hola']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ContentSid Required', $result['error']);
    }
}
