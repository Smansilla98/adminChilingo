<?php

namespace Tests\Feature;

use App\Models\WhatsappMensaje;
use App\Services\WhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class WhatsAppStatusTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.whatsapp_from' => '+14155238886',
            'services.twilio.status_callback_url' => 'http://localhost/webhooks/twilio/whatsapp-status',
            'app.url' => 'http://localhost',
        ]);

        if (! extension_loaded('pdo_sqlite')) {
            return;
        }

        Schema::dropIfExists('whatsapp_mensajes');
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alumno_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('cuota_id')->nullable();
            $table->string('telefono', 32);
            $table->string('tipo', 32);
            $table->string('twilio_sid', 64)->unique();
            $table->string('status', 32);
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_twilio_acepta_guarda_sid_y_estado_inicial(): void
    {
        $this->requireSqlite();
        $msg = (object) ['sid' => 'SM123aceptado', 'status' => 'queued'];
        $messages = Mockery::mock();
        $messages->shouldReceive('create')
            ->once()
            ->withArgs(function (string $to, array $payload) {
                return $to === 'whatsapp:+5491112345678'
                    && $payload['body'] === 'Hola'
                    && ($payload['statusCallback'] ?? null) === 'http://localhost/webhooks/twilio/whatsapp-status';
            })
            ->andReturn($msg);
        $client = Mockery::mock(Client::class);
        $client->messages = $messages;

        $service = new WhatsAppService();
        $service->setClient($client);
        $result = $service->send('Hola', '91112345678', [
            'tipo' => WhatsappMensaje::TIPO_CUOTA,
            'alumno_id' => 7,
            'cuota_id' => 3,
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted_by_provider']);
        $this->assertFalse($result['delivered_to_recipient']);
        $this->assertSame('SM123aceptado', $result['sid']);
        $this->assertSame('queued', $result['status']);

        $row = WhatsappMensaje::query()->where('twilio_sid', 'SM123aceptado')->first();
        $this->assertNotNull($row);
        $this->assertSame('queued', $row->status);
        $this->assertSame('+5491112345678', $row->telefono);
        $this->assertSame(WhatsappMensaje::TIPO_CUOTA, $row->tipo);
        $this->assertSame(7, $row->alumno_id);
        $this->assertSame(3, $row->cuota_id);
    }

    public function test_callback_delivered(): void
    {
        $this->requireSqlite();
        $row = $this->seedMensaje('SMdeliv');
        $this->postCallback('SMdeliv', 'delivered')->assertOk();
        $row->refresh();
        $this->assertSame('delivered', $row->status);
        $this->assertNotNull($row->delivered_at);
        $this->assertTrue($row->isDeliveredToRecipient());
        $this->assertSame('Entregado', $row->etiquetaEstado());
    }

    public function test_callback_failed_guarda_error(): void
    {
        $this->requireSqlite();
        $row = $this->seedMensaje('SMfail');
        $this->postCallback('SMfail', 'failed', '63016', 'Failed to send')->assertOk();
        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertSame('63016', $row->error_code);
        $this->assertSame('Failed to send', $row->error_message);
        $this->assertNotNull($row->failed_at);
        $this->assertSame('Fallido', $row->etiquetaEstado());
    }

    public function test_callback_duplicado_no_crea_otra_fila(): void
    {
        $this->requireSqlite();
        $this->seedMensaje('SMdup');
        $this->postCallback('SMdup', 'delivered')->assertOk();
        $this->postCallback('SMdup', 'delivered')->assertOk();
        $this->assertSame(1, WhatsappMensaje::query()->where('twilio_sid', 'SMdup')->count());
        $this->assertSame('delivered', WhatsappMensaje::query()->where('twilio_sid', 'SMdup')->value('status'));
    }

    public function test_callback_fuera_de_orden_no_retrocede(): void
    {
        $this->requireSqlite();
        $row = $this->seedMensaje('SMorder');
        $this->postCallback('SMorder', 'delivered')->assertOk();
        $this->postCallback('SMorder', 'sent')->assertOk();
        $row->refresh();
        $this->assertSame('delivered', $row->status);
    }

    public function test_callback_sin_firma_es_403(): void
    {
        $this->post('/webhooks/twilio/whatsapp-status', [
            'MessageSid' => 'SMsec',
            'MessageStatus' => 'delivered',
        ])->assertForbidden();
    }

    public function test_callback_sid_desconocido_no_falla_y_no_duplica(): void
    {
        $this->requireSqlite();
        $this->postCallback('SMnuevo', 'delivered')->assertOk();
        $this->postCallback('SMnuevo', 'delivered')->assertOk();
        $this->assertSame(1, WhatsappMensaje::query()->where('twilio_sid', 'SMnuevo')->count());
        $this->assertSame('delivered', WhatsappMensaje::query()->where('twilio_sid', 'SMnuevo')->value('status'));
    }

    private function requireSqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requiere pdo_sqlite.');
        }
    }

    private function seedMensaje(string $sid): WhatsappMensaje
    {
        return WhatsappMensaje::query()->create([
            'telefono' => '+5491112345678',
            'tipo' => WhatsappMensaje::TIPO_CUOTA,
            'twilio_sid' => $sid,
            'status' => WhatsappMensaje::STATUS_QUEUED,
            'accepted_at' => now(),
        ]);
    }

    private function postCallback(string $sid, string $status, ?string $errorCode = null, ?string $errorMessage = null)
    {
        $params = array_filter([
            'MessageSid' => $sid,
            'MessageStatus' => $status,
            'To' => 'whatsapp:+5491112345678',
            'ErrorCode' => $errorCode,
            'ErrorMessage' => $errorMessage,
        ], fn ($v) => $v !== null && $v !== '');

        $url = 'http://localhost/webhooks/twilio/whatsapp-status';
        $signature = (new RequestValidator('test-token'))->computeSignature($url, $params);

        return $this->withHeaders(['X-Twilio-Signature' => $signature])
            ->post('/webhooks/twilio/whatsapp-status', $params);
    }
}
