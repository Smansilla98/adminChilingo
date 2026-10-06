<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppRecordatoriosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lista_a_los_impagos_con_su_nombre(): void
    {
        config([
            'services.twilio.account_sid' => 'ACtest',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.whatsapp_from' => '+14155238886',
        ]);
        $sede = Sede::query()->create(['nombre' => 'Sede', 'activo' => true]);
        Alumno::query()->create(['nombre_apellido' => 'Ana Pérez', 'telefono' => '1155550000', 'sede_id' => $sede->id, 'activo' => true]);
        Alumno::query()->create(['nombre_apellido' => 'Sin Teléfono', 'telefono' => '', 'sede_id' => $sede->id, 'activo' => true]);
        Cuota::query()->create([
            'nombre' => 'Cuota del mes', 'año' => (int) date('Y'), 'mes' => (int) date('n'),
            'monto' => '15000.00', 'alcance' => 'general', 'activo' => true,
        ]);

        $this->artisan('whatsapp:recordatorios', ['--cuotas' => true, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Ana Pérez - 1155550000')
            ->doesntExpectOutputToContain('Sin Teléfono')
            ->expectsOutputToContain('Enviados: 1, Errores: 0')
            ->assertSuccessful();
    }
}
