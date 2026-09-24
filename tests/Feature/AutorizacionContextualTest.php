<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Gasto;
use App\Models\InventarioItem;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Los permisos valen donde corresponde: sede, bloque o toda la escuela.
 */
class AutorizacionContextualTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function alumnoEn($bloque, string $nombre = 'Alumno'): Alumno
    {
        return $this->inscribirAlumno($this->persona($nombre.' '.$bloque->id), $bloque);
    }

    private function item($sede, string $nombre = 'Surdo'): InventarioItem
    {
        return InventarioItem::query()->create([
            'sede_id' => $sede->id, 'tipo' => 'instrumento', 'nombre' => $nombre, 'cantidad' => 1,
            'propietario_tipo' => 'escuela', 'estado' => 'bueno', 'es_consumible' => false,
        ]);
    }

    /**
     * Test obligatorio: una sola persona y una sola cuenta con cinco funciones.
     */
    public function test_caso_combinado_una_persona_cinco_funciones(): void
    {
        $banfield = $this->sede('Banfield');
        $palomar = $this->sede('Palomar');
        $quilmes = $this->sede('Quilmes');
        $varela = $this->sede('Varela');
        $bBanfield = $this->bloque($banfield, 'Banfield 3');
        $bPalomarA = $this->bloque($palomar, 'Palomar A');
        $bPalomarB = $this->bloque($palomar, 'Palomar B');
        $bQuilmes = $this->bloque($quilmes, 'Quilmes 1');
        $bVarela = $this->bloque($varela, 'Varela 1');

        $juan = $this->usuario('Juan Pérez');
        $persona = $juan->persona;
        $this->inscribirAlumno($persona, $bBanfield);                   // Alumno — Banfield
        $this->asignarDocente($persona, $bPalomarA);                    // Profesor — Palomar A
        $this->rolDocenteEnSede($persona, $quilmes, 'coordinador');     // Coordinador — Quilmes
        $this->asignarRol($persona, 'encargado', 'sede', $varela);      // Encargado — Varela
        $this->asignarRol($persona, 'contador');                        // Contador — Global
        $juan = $juan->fresh();

        // Una sola identidad y una sola cuenta.
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, \App\Models\Persona::query()->where('nombre', 'Juan Pérez')->count());
        foreach (['alumno', 'profesor', 'coordinador', 'encargado', 'contador'] as $rol) {
            $this->assertTrue($juan->acceso()->tieneRol($rol), "Debería tener el rol $rol");
        }

        // Profesor en Palomar A: ve y toma asistencia solo en su bloque.
        $alumnoPalomarA = $this->alumnoEn($bPalomarA);
        $alumnoPalomarB = $this->alumnoEn($bPalomarB);
        $this->actingAs($juan)->get(route('alumnos.show', $alumnoPalomarA))->assertOk();
        $this->actingAs($juan)->get(route('alumnos.show', $alumnoPalomarB))->assertForbidden();
        $this->actingAs($juan)->post(route('asistencias.store'), [
            'bloque_id' => $bPalomarA->id, 'fecha' => now()->toDateString(),
            'asistencias' => [['alumno_id' => $alumnoPalomarA->id, 'tipo_asistencia' => 'presente']],
        ])->assertRedirect();
        $this->assertDatabaseHas('asistencias', ['alumno_id' => $alumnoPalomarA->id, 'registrado_por' => $juan->id]);
        $this->actingAs($juan)->post(route('asistencias.store'), [
            'bloque_id' => $bVarela->id, 'fecha' => now()->toDateString(), 'asistencias' => [],
        ])->assertForbidden();

        // Coordinador en Quilmes: gestiona alumnos y la sede de Quilmes, no otras.
        $alumnoQuilmes = $this->alumnoEn($bQuilmes);
        $this->actingAs($juan)->get(route('alumnos.show', $alumnoQuilmes))->assertOk();
        $this->actingAs($juan)->get(route('alumnos.edit', $alumnoQuilmes))->assertOk();
        $this->actingAs($juan)->get(route('sedes.edit', $quilmes))->assertOk();
        $this->actingAs($juan)->get(route('sedes.edit', $palomar))->assertForbidden();
        $this->actingAs($juan)->delete(route('sedes.destroy', $quilmes))->assertForbidden();
        $this->actingAs($juan)->get(route('bloques.edit', $bQuilmes))->assertOk();
        $this->actingAs($juan)->delete(route('bloques.destroy', $bPalomarB))->assertForbidden();

        // Alumno en Banfield: no ve a sus compañeros, sí su propia ficha.
        $companero = $this->alumnoEn($bBanfield, 'Compañero');
        $this->actingAs($juan)->get(route('alumnos.show', $companero))->assertForbidden();
        $propio = Alumno::query()->where('persona_id', $persona->id)->firstOrFail();
        $this->actingAs($juan)->get(route('alumnos.show', $propio))->assertOk();

        // Encargado en Varela: inventario de Varela sí; de Banfield no.
        $itemVarela = $this->item($varela);
        $itemBanfield = $this->item($banfield);
        $this->actingAs($juan)->get(route('inventarios.show', $itemVarela))->assertOk();
        $this->actingAs($juan)->put(route('inventarios.update', $itemVarela), [
            'sede_id' => $varela->id, 'tipo' => 'instrumento', 'nombre' => 'Surdo 20"', 'cantidad' => 1,
            'propietario_tipo' => 'escuela', 'estado' => 'regular',
        ])->assertRedirect();
        $this->assertSame('regular', $itemVarela->fresh()->estado);
        $this->actingAs($juan)->get(route('inventarios.show', $itemBanfield))->assertForbidden();
        $this->actingAs($juan)->get(route('inventarios.index'))->assertOk()
            ->assertSee($itemVarela->codigo ?? 'Surdo 20"')
            ->assertDontSee('CHL-'.str_pad((string) $itemBanfield->id, 4, '0', STR_PAD_LEFT));

        // Contador global: consulta finanzas, pero no registra pagos ni administra usuarios.
        $this->actingAs($juan)->get(route('pagos.index'))->assertOk();
        $this->actingAs($juan)->get(route('cuotas.index'))->assertOk();
        $this->actingAs($juan)->get(route('gastos.index'))->assertOk();
        $this->actingAs($juan)->get(route('reportes.index'))->assertOk();
        $this->actingAs($juan)->get(route('pagos.create'))->assertForbidden();
        $this->actingAs($juan)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_profesor_solo_accede_a_sus_bloques(): void
    {
        $sede = $this->sede('Palomar');
        $mio = $this->bloque($sede, 'Mío');
        $ajeno = $this->bloque($sede, 'Ajeno');
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $mio);
        $profe = $profe->fresh();

        $this->actingAs($profe)->get(route('alumnos.show', $this->alumnoEn($mio)))->assertOk();
        $this->actingAs($profe)->get(route('alumnos.show', $this->alumnoEn($ajeno)))->assertForbidden();
        $this->actingAs($profe)->get(route('alumnos.create'))->assertForbidden();
        $this->actingAs($profe)->get(route('pagos.index'))->assertOk();       // su vista acotada
        $this->actingAs($profe)->get(route('gastos.index'))->assertForbidden();
        $this->actingAs($profe)->get(route('sedes.index'))->assertForbidden();
    }

    public function test_coordinador_no_puede_editar_ni_borrar_otra_sede_por_id(): void
    {
        $quilmes = $this->sede('Quilmes');
        $palomar = $this->sede('Palomar');
        $bPalomar = $this->bloque($palomar);
        $coord = $this->usuario('Coord');
        $this->rolDocenteEnSede($coord->persona, $quilmes, 'coordinador');
        $coord = $coord->fresh();

        $this->actingAs($coord)->put(route('sedes.update', $palomar), ['nombre' => 'Hackeada'])->assertForbidden();
        $this->actingAs($coord)->delete(route('bloques.destroy', $bPalomar))->assertForbidden();
        $this->actingAs($coord)->put(route('bloques.update', $bPalomar), [
            'nombre' => 'X', 'año' => 1, 'sede_id' => $quilmes->id, 'cantidad_max_alumnos' => 10,
        ])->assertForbidden();
        $this->assertSame('Palomar', $palomar->fresh()->nombre);
        $this->assertDatabaseHas('bloques', ['id' => $bPalomar->id]);
    }

    public function test_coordinador_no_puede_mover_bloque_propio_a_sede_ajena(): void
    {
        $quilmes = $this->sede('Quilmes');
        $palomar = $this->sede('Palomar');
        $bQuilmes = $this->bloque($quilmes);
        $coord = $this->usuario('Coord');
        $this->rolDocenteEnSede($coord->persona, $quilmes, 'coordinador');

        $this->actingAs($coord->fresh())->put(route('bloques.update', $bQuilmes), [
            'nombre' => 'Movido', 'año' => 1, 'sede_id' => $palomar->id, 'cantidad_max_alumnos' => 10,
        ])->assertForbidden();
        $this->assertSame($quilmes->id, $bQuilmes->fresh()->sede_id);
    }

    public function test_contador_de_sede_solo_ve_gastos_de_su_sede(): void
    {
        $quilmes = $this->sede('Quilmes');
        $palomar = $this->sede('Palomar');
        $contador = $this->usuario('Contadora');
        $this->asignarRol($contador->persona, 'contador', 'sede', $quilmes);
        $gQuilmes = Gasto::query()->create(['sede_id' => $quilmes->id, 'fecha' => now(), 'tipo' => 'alquiler', 'monto' => 100, 'descripcion' => 'Alquiler Quilmes']);
        $gPalomar = Gasto::query()->create(['sede_id' => $palomar->id, 'fecha' => now(), 'tipo' => 'alquiler', 'monto' => 200, 'descripcion' => 'Alquiler Palomar']);

        $this->actingAs($contador->fresh())->get(route('gastos.show', $gQuilmes))->assertOk();
        $this->actingAs($contador->fresh())->get(route('gastos.show', $gPalomar))->assertForbidden();
        $this->actingAs($contador->fresh())->get(route('gastos.index'))->assertOk()
            ->assertSee('Alquiler Quilmes')->assertDontSee('Alquiler Palomar');
    }

    public function test_encargado_de_inventario_no_accede_a_finanzas_ni_alumnos(): void
    {
        $varela = $this->sede('Varela');
        $user = $this->usuario('Encargado');
        $this->asignarRol($user->persona, 'responsable_de_inventario', 'sede', $varela);
        $user = $user->fresh();

        $this->actingAs($user)->get(route('inventarios.index'))->assertOk();
        $this->actingAs($user)->get(route('inventarios.create'))->assertOk();
        $this->actingAs($user)->get(route('pagos.index'))->assertForbidden();
        $this->actingAs($user)->get(route('alumnos.index'))->assertForbidden();
    }

    public function test_administrador_accede_globalmente_pero_no_asigna_administradores(): void
    {
        $admin = $this->admin();
        $otro = $this->usuario('Otro');
        $sede = $this->sede('Banfield');

        foreach (['alumnos.index', 'pagos.index', 'gastos.index', 'inventarios.index', 'usuarios.index', 'personas.index', 'reportes.index'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
        $this->actingAs($admin)->get(route('sedes.edit', $sede))->assertOk();

        $this->actingAs($admin)->post(route('usuarios.asignaciones.store', $otro), [
            'tipo' => 'rol', 'nombre' => 'administrador', 'ambito' => 'global',
        ])->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->post(route('usuarios.asignaciones.store', $otro), [
            'tipo' => 'rol', 'nombre' => 'contador', 'ambito' => 'global',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($otro->fresh()->acceso()->tieneRol('contador'));
    }

    public function test_superadmin_puede_asignar_administradores(): void
    {
        $super = $this->usuario('Super');
        $this->asignarRol($super->persona, 'superadministrador');
        $otro = $this->usuario('Otro');

        $this->actingAs($super->fresh())->post(route('usuarios.asignaciones.store', $otro), [
            'tipo' => 'rol', 'nombre' => 'administrador', 'ambito' => 'global',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($otro->fresh()->isAdmin());
    }

    public function test_no_se_puede_quitar_el_ultimo_superadministrador(): void
    {
        $super = $this->usuario('Super');
        $asignacion = $this->asignarRol($super->persona, 'superadministrador');

        $this->actingAs($super->fresh())
            ->delete(route('usuarios.asignaciones.destroy', [$super, $asignacion]))
            ->assertSessionHasErrors('asignacion');
        $this->assertDatabaseHas('asignaciones', ['id' => $asignacion->id]);
    }

    public function test_roles_derivados_no_se_asignan_a_mano_y_el_alcance_se_valida(): void
    {
        $admin = $this->admin();
        $otro = $this->usuario('Otro');

        $this->actingAs($admin)->post(route('usuarios.asignaciones.store', $otro), [
            'tipo' => 'rol', 'nombre' => 'alumno', 'ambito' => 'global',
        ])->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->post(route('usuarios.asignaciones.store', $otro), [
            'tipo' => 'rol', 'nombre' => 'encargado', 'ambito' => 'global',
        ])->assertSessionHasErrors('ambito');
    }

    public function test_permiso_suelto_con_alcance_de_sede(): void
    {
        $quilmes = $this->sede('Quilmes');
        $palomar = $this->sede('Palomar');
        $user = $this->usuario('Suelto');
        $this->asignarPermiso($user->persona, 'inventario.view', 'sede', $quilmes);
        $user = $user->fresh();

        $this->actingAs($user)->get(route('inventarios.show', $this->item($quilmes)))->assertOk();
        $this->actingAs($user)->get(route('inventarios.show', $this->item($palomar)))->assertForbidden();
    }

    public function test_cuenta_desactivada_pierde_acceso(): void
    {
        $admin = $this->admin();
        $user = $this->usuario('Operador');
        $this->asignarRol($user->persona, 'administrativo');

        $this->actingAs($admin)->post(route('usuarios.estado', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->activo);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_asignacion_vencida_no_otorga_permisos(): void
    {
        $user = $this->usuario('Temporal');
        $a = $this->asignarRol($user->persona, 'contador');
        $a->update(['hasta' => now()->subDay()->toDateString()]);

        $this->assertFalse($user->fresh()->acceso()->puede('pagos.view'));
    }

    public function test_pago_de_alumno_ajeno_no_se_ve_cambiando_el_id(): void
    {
        $palomar = $this->sede('Palomar');
        $mio = $this->bloque($palomar, 'Mío');
        $ajeno = $this->bloque($palomar, 'Ajeno');
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $mio);

        $cuota = \App\Models\Cuota::query()->create(['nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pagoAjeno = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pagoAjeno->detalles()->create(['alumno_id' => $this->alumnoEn($ajeno)->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);
        $pagoMio = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pagoMio->detalles()->create(['alumno_id' => $this->alumnoEn($mio)->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);

        $this->actingAs($profe->fresh())->get(route('pagos.show', $pagoMio))->assertOk();
        $this->actingAs($profe->fresh())->get(route('pagos.show', $pagoAjeno))->assertForbidden();
    }
}
