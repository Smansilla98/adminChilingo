<?php

namespace Tests\Unit;

use App\Models\WhatsappMensaje;
use Tests\TestCase;

class WhatsappMensajeInMemory extends WhatsappMensaje
{
    public $exists = true;

    public function save(array $options = []): bool
    {
        $this->syncOriginal();

        return true;
    }
}

class WhatsappMensajeEstadoTest extends TestCase
{
    public function test_delivered_luego_sent_no_retrocede(): void
    {
        $mensaje = $this->mensaje();
        $this->assertTrue($mensaje->applyTwilioCallback('delivered'));
        $this->assertSame('delivered', $mensaje->status);
        $this->assertFalse($mensaje->applyTwilioCallback('sent'));
        $this->assertSame('delivered', $mensaje->status);
        $this->assertTrue($mensaje->isDeliveredToRecipient());
    }

    public function test_failed_guarda_codigo_y_mensaje(): void
    {
        $mensaje = $this->mensaje();
        $this->assertTrue($mensaje->applyTwilioCallback('failed', '63016', 'Failed to send'));
        $this->assertSame('failed', $mensaje->status);
        $this->assertSame('63016', $mensaje->error_code);
        $this->assertSame('Failed to send', $mensaje->error_message);
        $this->assertNotNull($mensaje->failed_at);
        $this->assertSame('Fallido', $mensaje->etiquetaEstado());
    }

    public function test_callback_duplicado_delivered_no_cambia_estado(): void
    {
        $mensaje = $this->mensaje();
        $this->assertTrue($mensaje->applyTwilioCallback('delivered'));
        $this->assertFalse($mensaje->applyTwilioCallback('delivered'));
        $this->assertSame('delivered', $mensaje->status);
    }

    public function test_failed_no_es_pisado_por_sent(): void
    {
        $mensaje = $this->mensaje();
        $mensaje->applyTwilioCallback('failed', '63016', 'err');
        $this->assertFalse($mensaje->applyTwilioCallback('sent'));
        $this->assertSame('failed', $mensaje->status);
    }

    public function test_delivered_no_es_pisado_por_failed(): void
    {
        $mensaje = $this->mensaje();
        $mensaje->applyTwilioCallback('delivered');
        $this->assertFalse($mensaje->applyTwilioCallback('failed', '1', 'x'));
        $this->assertSame('delivered', $mensaje->status);
    }

    private function mensaje(): WhatsappMensajeInMemory
    {
        $mensaje = new WhatsappMensajeInMemory;
        $mensaje->forceFill([
            'telefono' => '+5491112345678',
            'tipo' => WhatsappMensaje::TIPO_CUOTA,
            'twilio_sid' => 'SMtest',
            'status' => WhatsappMensaje::STATUS_QUEUED,
        ]);
        $mensaje->syncOriginal();

        return $mensaje;
    }
}
