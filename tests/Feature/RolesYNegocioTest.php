<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use App\Services\PagoDesdeComprobanteService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BusinessSchema;
use Tests\TestCase;

class RolesYNegocioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requiere extensión pdo_sqlite (php8.3-sqlite3) para tests de negocio con DB.');
        }

        BusinessSchema::migrateMinimal();
    }

    public function test_profesor_no_accede_a_reportes(): void
    {
        $user = User::create([
            'name' => 'Profe',
            'username' => 'profe1',
            'email' => 'profe@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
        ]);
        $user->assignRole('profesor');

        $this->actingAs($user)
            ->get(route('reportes.index'))
            ->assertForbidden();
    }

    public function test_admin_accede_a_reportes_y_export(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'username' => 'admin1',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('reportes.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('reportes.export.pdf'))
            ->assertOk();
    }

    public function test_coordinador_sede_puede_ver_reportes(): void
    {
        $user = User::create([
            'name' => 'Coord',
            'username' => 'coord1',
            'email' => 'coord@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
        ]);
        $user->assignRole('profesor');
        $user->assignRole('coordinador_sede');

        $this->actingAs($user)
            ->get(route('reportes.index'))
            ->assertOk();
    }

    public function test_aprobar_comprobante_crea_pago(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin2',
            'email' => 'admin2@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        $sede = Sede::create(['nombre' => 'Sede Test']);
        $prof = Profesor::create(['nombre' => 'Profe', 'activo' => true]);
        $bloque = Bloque::create([
            'nombre' => 'Bloque A',
            'sede_id' => $sede->id,
            'profesor_id' => $prof->id,
            'activo' => true,
        ]);
        $alumno = Alumno::create([
            'nombre_apellido' => 'Alumno Test',
            'sede_id' => $sede->id,
            'bloque_id' => $bloque->id,
            'activo' => true,
            'instrumento_principal' => 'Timbal',
        ]);
        $cuota = Cuota::create([
            'nombre' => 'Cuota marzo',
            'monto' => 1000,
            'mes' => 3,
            'año' => 2026,
            'bloque_id' => $bloque->id,
            'alcance' => 'bloque',
            'activo' => true,
        ]);

        $comprobante = ComprobanteCuotaAlumno::create([
            'alumno_id' => $alumno->id,
            'sede_id' => $sede->id,
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 1000,
            'estado' => 'pendiente',
            'notas' => 'test',
        ]);
        ComprobanteCuotaAlumnoItem::create([
            'comprobante_cuota_alumno_id' => $comprobante->id,
            'cuota_id' => $cuota->id,
            'bloque_id' => $bloque->id,
            'monto' => 1000,
        ]);

        $service = app(PagoDesdeComprobanteService::class);
        $result = $service->aprobar($comprobante->fresh(['items.cuota', 'alumno']), $admin->id, false);

        $this->assertNotNull($result['pago']->id);
        $this->assertTrue(PagoDetalle::query()->where('pago_id', $result['pago']->id)->exists());
        $this->assertSame('pagado', $comprobante->fresh()->estado);
        $this->assertSame($result['pago']->id, $comprobante->fresh()->pago_id);
    }

    public function test_asistencia_update_or_create(): void
    {
        $sede = Sede::create(['nombre' => 'Sede Asist']);
        $bloque = Bloque::create(['nombre' => 'B1', 'sede_id' => $sede->id, 'activo' => true]);
        $alumno = Alumno::create([
            'nombre_apellido' => 'A1',
            'sede_id' => $sede->id,
            'bloque_id' => $bloque->id,
            'activo' => true,
        ]);

        Asistencia::updateOrCreate(
            [
                'alumno_id' => $alumno->id,
                'bloque_id' => $bloque->id,
                'fecha' => '2026-03-10',
            ],
            [
                'tipo_asistencia' => 'presente',
                'presente' => true,
            ]
        );

        $this->assertDatabaseHas('asistencias', [
            'alumno_id' => $alumno->id,
            'bloque_id' => $bloque->id,
            'tipo_asistencia' => 'presente',
        ]);
    }
}
