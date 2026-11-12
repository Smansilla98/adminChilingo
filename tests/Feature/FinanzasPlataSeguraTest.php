<?php

namespace Tests\Feature;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Auditoria;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Integridad de la plata: ediciones auditadas, FKs que no borran pagos, papelera,
 * registro único web/API con idempotencia y control de doble cobro.
 */
class FinanzasPlataSeguraTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function tesorero()
    {
        $u = $this->usuario('Tesorería');
        $this->asignarRol($u->persona, 'tesorero');

        return $u->fresh();
    }

    private function cuota(array $datos = []): Cuota
    {
        return Cuota::query()->create($datos + ['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'general', 'activo' => true]);
    }

    public function test_editar_un_pago_deja_auditoria_completa_antes_y_despues(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $beto = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());

        $id = $this->postJson('/api/v1/pagos', [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ])->assertCreated()->json('data.id');
        $detalleViejo = PagoDetalle::query()->where('pago_id', $id)->value('id');

        $this->putJson("/api/v1/pagos/{$id}", [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $beto->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ])->assertOk();

        $registro = Auditoria::query()->where('entidad_tipo', 'Pago')->where('entidad_id', $id)->where('accion', 'editado')->firstOrFail();
        $this->assertSame($ana->id, $registro->datos_anteriores['lineas'][0]['alumno_id']);
        $this->assertSame($beto->id, $registro->datos_nuevos['lineas'][0]['alumno_id']);
        // La línea quitada quedó registrada una por una (no borrado en masa).
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'PagoDetalle', 'entidad_id' => $detalleViejo, 'accion' => 'deleted']);
    }

    public function test_un_pago_anulado_no_se_edita(): void
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $pago->forceFill(['anulado_at' => now(), 'motivo_anulacion' => 'error de carga'])->save();
        Sanctum::actingAs($this->admin());

        $r = $this->putJson("/api/v1/pagos/{$pago->id}", [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 100,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 100]],
        ]);
        $this->assertContains($r->status(), [403, 422]);
        $this->assertSame('24000.00', (string) $pago->fresh()->monto_total);
    }

    public function test_gasto_va_a_la_papelera_con_motivo_y_se_restaura(): void
    {
        $admin = $this->admin();
        $gasto = \App\Models\Gasto::query()->create(['fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 5000, 'descripcion' => 'Parches']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/gastos/{$gasto->id}", ['motivo' => 'Cargado dos veces'])->assertOk();
        $this->assertSoftDeleted('gastos', ['id' => $gasto->id, 'eliminado_por' => $admin->id, 'motivo_eliminacion' => 'Cargado dos veces']);
        $this->assertSame(0, (int) $this->getJson('/api/v1/gastos')->json('meta.total'));
        $this->assertSame(0.0, (float) $this->getJson('/api/v1/gastos')->json('total_monto'));
        $this->getJson('/api/v1/gastos?papelera=1')->assertOk()->assertJsonPath('data.0.motivo_eliminacion', 'Cargado dos veces');

        $this->postJson("/api/v1/gastos/{$gasto->id}/restaurar")->assertOk()->assertJsonPath('data.eliminado', false);
        $this->assertNotSoftDeleted('gastos', ['id' => $gasto->id]);

        // Web: papelera y restaurar.
        $this->actingAs($admin)->delete(route('gastos.destroy', $gasto), ['motivo' => 'Error'])->assertRedirect(route('gastos.index'));
        $this->actingAs($admin)->get(route('gastos.index', ['papelera' => 1]))->assertOk()->assertSee('Restaurar');
        $this->actingAs($admin)->post(route('gastos.restaurar', $gasto->id))->assertRedirect(route('gastos.index'));
        $this->assertNotSoftDeleted('gastos', ['id' => $gasto->id]);
    }

    public function test_gasto_de_la_gira_va_a_la_papelera(): void
    {
        $admin = $this->admin();
        $gasto = \App\Models\VillaGesellGasto::query()->create(['tipo' => 'nafta', 'concepto' => 'Ida', 'monto' => 1000, 'modo' => 'total']);

        $this->actingAs($admin)->delete(route('villa-gesell.gastos.destroy', $gasto), ['motivo' => 'Duplicado'])->assertRedirect();
        $this->assertSoftDeleted('villa_gesell_gastos', ['id' => $gasto->id, 'motivo_eliminacion' => 'Duplicado']);
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'VillaGesellGasto', 'entidad_id' => $gasto->id, 'accion' => 'deleted']);
        $this->actingAs($admin)->get(route('villa-gesell.gastos.index', ['papelera' => 1]))->assertOk()->assertSee('Duplicado');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/villa-gesell/gastos/papelera')->assertOk()->assertJsonPath('data.0.id', $gasto->id);
        $this->postJson("/api/v1/villa-gesell/gastos/{$gasto->id}/restaurar")->assertOk();
        $this->assertNotSoftDeleted('villa_gesell_gastos', ['id' => $gasto->id]);
    }

    public function test_doble_envio_del_formulario_web_no_duplica_el_pago(): void
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        $tesorero = $this->tesorero();
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $datos = [
            'client_uuid' => $uuid,
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'monto_abono_profesor' => '',
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ];

        $this->actingAs($tesorero)->get(route('pagos.create'))->assertOk()->assertSee('name="client_uuid"', false);
        $primero = $this->actingAs($tesorero)->post(route('pagos.store'), $datos);
        $pago = Pago::query()->sole();
        $primero->assertRedirect(route('pagos.show', $pago));
        $this->actingAs($tesorero)->post(route('pagos.store'), $datos)
            ->assertRedirect(route('pagos.show', $pago))->assertSessionHas('success', fn ($m) => str_contains($m, 'no se duplicó'));
        $this->assertSame(1, Pago::query()->count());
        $this->assertSame(1, PagoDetalle::query()->count());
    }

    public function test_dos_registros_del_mismo_pago_por_api_quedan_en_uno(): void
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());
        $datos = [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ];

        // Misma clave por encabezado (no UUID): el reintento devuelve el mismo pago.
        $id = $this->withHeader('Idempotency-Key', 'caja-palomar-0001')->postJson('/api/v1/pagos', $datos)->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', 'caja-palomar-0001')->postJson('/api/v1/pagos', $datos)->assertOk()
            ->assertJsonPath('duplicado', true)->assertJsonPath('data.id', $id);

        // Otra clave, misma cuota ya saldada: se rechaza (no se cobra dos veces).
        $this->withHeader('Idempotency-Key', 'caja-palomar-0002')->postJson('/api/v1/pagos', $datos)->assertStatus(422);
        $this->assertSame(1, Pago::query()->count());

        // La clave de otra persona no devuelve un pago ajeno.
        Sanctum::actingAs($this->admin('Otra'));
        $this->withHeader('Idempotency-Key', 'caja-palomar-0001')->postJson('/api/v1/pagos', $datos)->assertStatus(422)->assertJsonValidationErrors('client_uuid');
    }

    public function test_borrar_un_alumno_o_una_cuota_con_pagos_no_borra_la_plata(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = $this->cuota();
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $comprobante = \App\Models\ComprobanteCuotaAlumno::query()->create([
            'alumno_id' => $ana->id, 'fecha_pago' => now()->toDateString(), 'monto_total' => 24000, 'estado' => 'pendiente',
        ]);
        \App\Models\ComprobanteCuotaAlumnoItem::query()->create([
            'comprobante_cuota_alumno_id' => $comprobante->id, 'cuota_id' => $cuota->id, 'bloque_id' => $bloque->id, 'monto' => 24000,
        ]);

        // Por la aplicación: mensaje claro.
        $this->actingAs($this->admin())->delete(route('alumnos.destroy', $ana))->assertSessionHasErrors('eliminar');

        // Aunque alguien se saltee la aplicación, la base no arrastra los pagos.
        foreach ([fn () => $ana->delete(), fn () => $cuota->delete(), fn () => $bloque->delete()] as $borrar) {
            try {
                \Illuminate\Support\Facades\DB::transaction($borrar);
                $this->fail('La base debió rechazar el borrado.');
            } catch (\Illuminate\Database\QueryException) {
                // esperado
            }
        }
        $this->assertDatabaseHas('pago_detalles', ['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $this->assertDatabaseHas('comprobante_cuota_alumno_items', ['cuota_id' => $cuota->id]);
        $this->assertDatabaseHas('alumnos', ['id' => $ana->id]);
    }

    public function test_reglas_de_cobro_recargo_familiar_y_adelantado_en_el_estado_de_cuenta(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $beto = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $vence = now()->subDays(10)->startOfDay();
        $cuota = Cuota::query()->create([
            'nombre' => 'Vencida', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 10000, 'alcance' => 'general',
            'activo' => true, 'fecha_vencimiento' => $vence->toDateString(),
        ]);

        // Sin reglas: nada cambia.
        $this->assertSame(10000.0, app(EstadoCuentaService::class)->paraAlumno($ana)['items'][0]['neto']);

        // Recargo del 10% desde 5 días de vencida; familia: Beto paga 80% (segundo integrante).
        $grupo = \App\Models\GrupoFamiliar::query()->create(['nombre' => 'Familia Pérez']);
        $grupo->alumnos()->attach([$ana->id, $beto->id]);
        \App\Models\Ajuste::guardar(\App\Domain\Finanzas\ReglasCobro::CLAVE, [
            'recargo' => ['activo' => true, 'tipo' => 'porcentaje', 'valor' => 10, 'dias' => 5],
            'familiar' => ['activo' => true, 'porcentaje' => 20],
            'adelantado' => ['activo' => false, 'porcentaje' => 0, 'dias' => 0],
        ]);
        $estado = app(EstadoCuentaService::class);

        $item = $estado->paraAlumno($ana)['items'][0];
        $this->assertSame(1000.0, $item['recargo']);
        $this->assertSame(11000.0, $item['neto']);
        $this->assertSame(0.0, $item['descuento_familiar'], 'el primer integrante paga completo');

        $itemBeto = $estado->paraAlumno($beto)['items'][0];
        $this->assertSame(2000.0, $itemBeto['descuento_familiar']);
        $this->assertSame(8800.0, $itemBeto['neto']); // 8000 tras la familia + recargo 10% sobre 8000
        $this->assertSame('Familia Pérez', $estado->paraAlumno($beto)['grupo_familiar']);

        // Pagado antes del día de recargo: no hay recargo.
        $pago = Pago::query()->create(['fecha_pago' => $vence->copy()->addDays(2)->toDateString(), 'monto_total' => 10000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 10000]);
        $pagada = $estado->paraAlumno($ana)['items'][0];
        $this->assertSame(0.0, $pagada['recargo']);
        $this->assertSame('pagada', $pagada['estado']);
    }

    public function test_gasto_adjunta_comprobante_y_lo_reemplaza(): void
    {
        \Illuminate\Support\Facades\Storage::fake('comprobantes');
        $admin = $this->admin();
        $datos = ['fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 800, 'descripcion' => 'Cuerdas'];

        $this->actingAs($admin)->post(route('gastos.store'), $datos + ['comprobante' => \Illuminate\Http\UploadedFile::fake()->create('ticket.pdf', 20, 'application/pdf')])
            ->assertRedirect(route('gastos.index'));
        $gasto = \App\Models\Gasto::query()->sole();
        $primero = $gasto->comprobante_path;
        $this->assertNotNull($primero);
        \Illuminate\Support\Facades\Storage::disk('comprobantes')->assertExists($primero);

        $this->actingAs($admin)->get(route('gastos.comprobante', $gasto))->assertOk();
        $this->actingAs($admin)->get(route('gastos.show', $gasto))->assertSee('Ver comprobante adjunto');

        $this->actingAs($admin)->put(route('gastos.update', $gasto), $datos + ['comprobante' => \Illuminate\Http\UploadedFile::fake()->image('foto.jpg')])
            ->assertRedirect(route('gastos.index'));
        $nuevo = $gasto->fresh()->comprobante_path;
        $this->assertNotSame($primero, $nuevo);
        \Illuminate\Support\Facades\Storage::disk('comprobantes')->assertMissing($primero);
        \Illuminate\Support\Facades\Storage::disk('comprobantes')->assertExists($nuevo);

        $this->actingAs($admin)->post(route('gastos.store'), $datos + ['comprobante' => \Illuminate\Http\UploadedFile::fake()->create('virus.exe', 5)])
            ->assertSessionHasErrors('comprobante');
    }

    /**
     * Dos registros simultáneos del mismo alumno y cuota: el control de duplicados corre
     * después de bloquear la cuota (SELECT ... FOR UPDATE), así que el segundo espera al primero
     * y ve su pago ya confirmado. Se verifica el orden de las consultas (MySQL; SQLite no tiene
     * bloqueos de fila, la prueba se omite allí).
     */
    public function test_registro_simultaneo_bloquea_la_cuota_antes_de_revisar_duplicados(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('El bloqueo de fila solo aplica en MySQL.');
        }
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        $sql = [];
        \Illuminate\Support\Facades\DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });

        app(\App\Domain\Finanzas\RegistroPagoService::class)->registrar([
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ], $this->tesorero());

        $bloqueo = collect($sql)->search(fn ($q) => str_contains($q, 'for update') && str_contains($q, '`cuotas`'));
        $duplicados = collect($sql)->search(fn ($q) => str_contains($q, '`pago_detalles`') && ! str_starts_with($q, 'insert'));
        $this->assertNotFalse($bloqueo, 'la cuota debe bloquearse con FOR UPDATE');
        $this->assertNotFalse($duplicados);
        $this->assertLessThan($duplicados, $bloqueo, 'el bloqueo va antes que la revisión de duplicados');
    }

    public function test_pantalla_de_reglas_de_cobro_guarda_y_valida(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('configuracion.reglas-cobro.edit'))->assertOk()->assertSee('Reglas de cobro');

        $this->actingAs($admin)->put(route('configuracion.reglas-cobro.update'), [
            'recargo' => ['activo' => '1', 'tipo' => 'fijo', 'valor' => '500', 'dias' => '7'],
            'familiar' => ['activo' => '1', 'porcentaje' => '15'],
        ])->assertSessionHas('success');
        $reglas = \App\Domain\Finanzas\ReglasCobro::actuales();
        $this->assertTrue($reglas['recargo']['activo']);
        $this->assertSame('fijo', $reglas['recargo']['tipo']);
        $this->assertSame(500.0, $reglas['recargo']['valor']);
        $this->assertSame(15.0, $reglas['familiar']['porcentaje']);
        $this->assertFalse($reglas['adelantado']['activo'], 'lo que no se marca queda apagado');

        // Recargo prendido sin valor: no se guarda.
        $this->actingAs($admin)->put(route('configuracion.reglas-cobro.update'), [
            'recargo' => ['activo' => '1', 'tipo' => 'porcentaje', 'valor' => ''],
        ])->assertSessionHasErrors('recargo.valor');
        $this->assertSame(500.0, \App\Domain\Finanzas\ReglasCobro::actuales()['recargo']['valor']);
    }

    /**
     * Pago parcial de punta a punta: caja cobra una parte, el cobro online cobra otra (con el monto
     * elegido), el saldo se ve en el estado de cuenta, nadie cobra de más y el abono docente suma
     * el total de la cuota (prorrateado por lo que se cobra).
     */
    public function test_pago_parcial_de_punta_a_punta_con_saldo_visible(): void
    {
        \App\Models\Ajuste::guardar('cobro', ['alias' => 'chilinga.cuotas', 'titular' => 'Asociación Civil'], null);
        $sede = $this->sede('Palomar');
        $sede->forceFill(['liquidacion_retencion_escuela' => 4000, 'liquidacion_porc_docente' => 100])->save();
        $bloque = $this->bloque($sede);
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = $this->cuota(['monto' => 10000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id]);
        $tesorero = $this->tesorero();
        $admin = $this->admin();
        $registro = app(\App\Domain\Finanzas\RegistroPagoService::class);
        $estado = app(EstadoCuentaService::class);
        $cobros = app(\App\Domain\Finanzas\CobroCuotasService::class);
        $aprobar = app(\App\Services\PagoDesdeComprobanteService::class);
        $linea = fn (float $monto) => ['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => $monto];
        $saldo = fn () => $estado->paraAlumno($ana)['items'][0];

        // 1) Caja: cobra 4.000 de 10.000.
        $registro->registrar(['fecha_pago' => now()->toDateString(), 'monto_total' => 4000, 'lineas' => [$linea(4000)], 'liquidar_profesor' => '1'], $tesorero);
        $this->assertSame('parcial', $saldo()['estado']);
        $this->assertSame(6000.0, $saldo()['saldo'], 'el saldo queda visible en el estado de cuenta');

        // 2) Caja no cobra más que el saldo.
        try {
            $registro->registrar(['fecha_pago' => now()->toDateString(), 'monto_total' => 7000, 'lineas' => [$linea(7000)], 'liquidar_profesor' => '1'], $tesorero);
            $this->fail('debió rechazar un cobro mayor al saldo');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, Pago::query()->count());
        }

        // 3) Online: el alumno elige pagar 2.000 de lo que debe (saldo 6.000).
        $c1 = $cobros->iniciar($admin, $ana, [$cuota->id], 'transferencia', [$cuota->id => 2000]);
        $this->assertSame('2000.00', (string) $c1->monto_total);
        try {
            $cobros->iniciar($admin, $ana, [$cuota->id], 'transferencia', [$cuota->id => 50000]);
            $this->fail('debió rechazar un monto mayor al saldo');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, \App\Models\ComprobanteCuotaAlumno::query()->count());
        }
        $aprobar->aprobar($c1->fresh(), $admin->id);
        $this->assertSame(4000.0, $saldo()['saldo'], '6.000 - 2.000');

        // 4) Online: el resto (sin monto elegido = saldo completo).
        $c2 = $cobros->iniciar($admin, $ana, [$cuota->id], 'transferencia');
        $this->assertSame('4000.00', (string) $c2->monto_total);
        $aprobar->aprobar($c2->fresh(), $admin->id);
        $this->assertSame('pagada', $saldo()['estado']);
        $this->assertSame(0.0, $saldo()['saldo']);
        $this->assertSame(3, Pago::query()->count(), 'caja + dos cobros online');

        // El abono docente se reparte según lo cobrado: entre las tres partes suma el abono de la cuota (6.000).
        $this->assertSame(6000.0, (float) PagoDetalle::query()->sum('abono_profesor'));

        // 5) Ya no hay nada para cobrar.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $cobros->iniciar($admin, $ana, [$cuota->id], 'transferencia');
    }
}
