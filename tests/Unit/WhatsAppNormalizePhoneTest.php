<?php

namespace Tests\Unit;

use App\Services\WhatsAppService;
use PHPUnit\Framework\TestCase;

class WhatsAppNormalizePhoneTest extends TestCase
{
    public function test_movil_argentino_con_9(): void
    {
        $this->assertSame('+549111234567', WhatsAppService::normalizePhone('9111234567'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('91112345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('091112345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('9 11 1234-5678'));
    }

    public function test_ya_internacional(): void
    {
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('+5491112345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('5491112345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('whatsapp:+5491112345678'));
    }

    public function test_caba_con_15_y_011(): void
    {
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('1512345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('0111512345678'));
        $this->assertSame('+5491112345678', WhatsAppService::normalizePhone('011 15 1234-5678'));
    }

    public function test_011_fijo_no_genera_mas_011(): void
    {
        $this->assertSame('+541142345678', WhatsAppService::normalizePhone('01142345678'));
    }

    public function test_vacio(): void
    {
        $this->assertSame('', WhatsAppService::normalizePhone(''));
        $this->assertSame('', WhatsAppService::normalizePhone('   '));
    }
}
