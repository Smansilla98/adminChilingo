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

    public function test_login_no_convierte_alumno_en_profesor(): void
    {
        $user = User::create([
            'name' => 'Alumno Login',
            'username' => 'alulogin',
            'email' => 'alu@test.local',
            'password' => Hash::make('password'),
            'role' => 'alumno',
        ]);
        $user->assignRole('alumno');

        $this->post('/login', [
            'username' => 'alulogin',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue($user->hasRole('alumno'));
        $this->assertFalse($user->hasRole('profesor'));
        $this->assertSame('alumno', $user->role);
    }

    public function test_profesor_no_ve_asistencias_de_otro_bloque(): void
    {
        $sede = Sede::create(['nombre' => 'Sede A']);
        $profA = Profesor::create(['nombre' => 'Profe A', 'activo' => true]);
        $profB = Profesor::create(['nombre' => 'Profe B', 'activo' => true]);
        $bloqueA = Bloque::create([
            'nombre' => 'Bloque A',
            'sede_id' => $sede->id,
            'profesor_id' => $profA->id,
            'activo' => true,
        ]);
        $bloqueB = Bloque::create([
            'nombre' => 'Bloque B',
            'sede_id' => $sede->id,
            'profesor_id' => $profB->id,
            'activo' => true,
        ]);
        $alumnoB = Alumno::create([
            'nombre_apellido' => 'Alumno B',
            'sede_id' => $sede->id,
            'bloque_id' => $bloqueB->id,
            'activo' => true,
        ]);
        Asistencia::create([
            'alumno_id' => $alumnoB->id,
            'bloque_id' => $bloqueB->id,
            'fecha' => '2026-03-10',
            'tipo_asistencia' => 'presente',
            'presente' => true,
        ]);

        $userA = User::create([
            'name' => 'Profe A',
            'username' => 'profea',
            'email' => 'profea@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
        ]);
        $userA->assignRole('profesor');
        $profA->user_id = $userA->id;
        $profA->save();

        $this->actingAs($userA)
            ->get(route('profesor.asistencias.matrix', ['vista' => 'lista']))
            ->assertOk()
            ->assertDontSee('Alumno B');
    }

    public function test_coordinador_area_no_ve_alumno_de_otra_sede(): void
    {
        $sedeA = Sede::create(['nombre' => 'Sede A', 'activo' => true]);
        $sedeB = Sede::create(['nombre' => 'Sede B', 'activo' => true]);
        $user = User::create([
            'name' => 'Coord Área',
            'username' => 'coordarea',
            'email' => 'ca@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
        ]);
        $user->assignRole('profesor');
        $user->assignRole('coordinador_area');
        $prof = Profesor::create(['nombre' => 'Coord Área', 'activo' => true, 'user_id' => $user->id]);
        $bloqueA = Bloque::create([
            'nombre' => 'Bloque A',
            'sede_id' => $sedeA->id,
            'profesor_id' => $prof->id,
            'activo' => true,
        ]);
        $bloqueB = Bloque::create([
            'nombre' => 'Bloque B',
            'sede_id' => $sedeB->id,
            'profesor_id' => null,
            'activo' => true,
        ]);
        $alumnoA = Alumno::create([
            'nombre_apellido' => 'Alumno Sede A',
            'sede_id' => $sedeA->id,
            'bloque_id' => $bloqueA->id,
            'activo' => true,
        ]);
        $alumnoB = Alumno::create([
            'nombre_apellido' => 'Alumno Sede B',
            'sede_id' => $sedeB->id,
            'bloque_id' => $bloqueB->id,
            'activo' => true,
        ]);

        $this->actingAs($user)->get(route('alumnos.show', $alumnoA))->assertOk();
        $this->actingAs($user)->get(route('alumnos.show', $alumnoB))->assertForbidden();
    }

    public function test_api_publica_alumnos_exige_dni(): void
    {
        $this->getJson(route('comprobante-cuota-public.api.alumnos', [
            'sede_id' => 1,
            'año' => 2026,
            'mes' => 3,
            'bloque_ids' => [1],
        ]))->assertStatus(422);
    }

    public function test_comprobante_publico_store_exige_token(): void
    {
        $this->from(route('comprobante-cuota-public.create'))
            ->post(route('comprobante-cuota-public.store'), [
                'sede_id' => 1,
                'año' => 2026,
                'mes' => 3,
                'fecha_pago' => '2026-03-01',
                'alumno_id' => 1,
                'dni' => '30111222',
                'bloque_ids' => [1],
            ])
            ->assertSessionHasErrors('lookup_token');
    }

    public function test_admin_crea_usuario_con_ficha_de_profesor(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin1',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('accesos.store'), [
                'name' => 'Nuevo Profe',
                'username' => 'nuevoprofe',
                'email' => 'nuevo@test.local',
                'password' => 'password12',
                'password_confirmation' => 'password12',
                'role' => 'profesor',
                'profesor_vinculo' => 'nuevo',
            ])
            ->assertRedirect(route('accesos.index', ['user_id' => User::query()->where('email', 'nuevo@test.local')->value('id')]));

        $creado = User::query()->where('email', 'nuevo@test.local')->first();
        $this->assertNotNull($creado);
        $this->assertTrue($creado->hasRole('profesor'));
        $this->assertDatabaseHas('profesores', [
            'user_id' => $creado->id,
            'nombre' => 'Nuevo Profe',
        ]);
    }

    public function test_admin_crea_profesor_con_usuario_y_contrasena(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin2',
            'email' => 'admin2@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('profesores.store'), [
                'nombre' => 'Docente Costa',
                'email' => 'costa@test.local',
                'activo' => '1',
                'cuenta_modo' => 'nueva',
                'login_username' => 'docentecosta',
                'login_password' => 'password12',
                'login_password_confirmation' => 'password12',
            ])
            ->assertRedirect(route('profesores.index'));

        $profesor = Profesor::query()->where('nombre', 'Docente Costa')->first();
        $this->assertNotNull($profesor);
        $this->assertNotNull($profesor->user);
        $this->assertSame('docentecosta', $profesor->user->username);
        $this->assertTrue($profesor->user->hasRole('profesor'));
        $this->assertTrue(Hash::check('password12', $profesor->user->password));
    }

    public function test_profesor_no_puede_crear_usuarios(): void
    {
        $user = User::create([
            'name' => 'Profe',
            'username' => 'profe2',
            'email' => 'profe2@test.local',
            'password' => Hash::make('password'),
            'role' => 'profesor',
        ]);
        $user->assignRole('profesor');

        $this->actingAs($user)
            ->get(route('accesos.create'))
            ->assertForbidden();
    }
}
