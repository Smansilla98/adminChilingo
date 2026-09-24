<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\Gasto;
use App\Models\InventarioItem;
use App\Models\OrdenCompra;
use App\Models\Pago;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Las fichas (show) y formularios (create/edit) rediseñados renderizan con datos
 * reales, conservan los nombres de campo que esperan los controladores y muestran
 * la estructura común: secciones, acciones al pie, resumen y pestañas.
 */
class FichasYFormulariosTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_fichas_renderizan_con_resumen_y_secciones(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Banfield');
        $bloque = $this->bloque($sede, 'Banfield 1');
        $bloque->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '19:30:00']);
        $maria = $this->usuario('María Gómez');
        $alumno = $this->inscribirAlumno($maria->persona, $bloque);
        $profe = $this->asignarDocente($this->usuario('Lucía Profe')->persona, $bloque);
        $cuota = Cuota::query()->create(['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);
        $evento = Evento::query()->create(['titulo' => 'Muestra', 'fecha' => now()->addWeek(), 'sede_id' => $sede->id, 'tipo_evento' => 'muestra']);
        $show = Show::query()->create(['titulo' => 'Show de primavera', 'fecha' => now()->addMonth(), 'lugar' => 'Plaza']);
        $gasto = Gasto::query()->create(['fecha' => now(), 'tipo' => array_key_first(Gasto::TIPOS), 'monto' => 500, 'descripcion' => 'Luz', 'sede_id' => $sede->id]);
        $item = InventarioItem::query()->create(['sede_id' => $sede->id, 'tipo' => 'instrumento', 'nombre' => 'Repique 12', 'codigo' => 'BAN-REP-1', 'propietario_tipo' => 'escuela', 'cantidad' => 1, 'estado' => 'bueno']);
        $orden = OrdenCompra::query()->create(['sede_id' => $sede->id, 'motivo' => array_key_first(OrdenCompra::MOTIVOS), 'estado' => 'borrador']);
        $orden->items()->create(['descripcion' => 'Parche 12', 'cantidad' => 2, 'unidad' => 'u', 'precio_estimado' => 100]);
        $asistencia = Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => now()->toDateString(), 'tipo_asistencia' => 'presente', 'presente' => true]);
        $comprobante = ComprobanteCuotaAlumno::query()->create(['alumno_id' => $alumno->id, 'sede_id' => $sede->id, 'fecha_pago' => now(), 'monto_total' => 1000, 'estado' => 'pendiente']);
        $comprobante->items()->create(['cuota_id' => $cuota->id, 'bloque_id' => $bloque->id, 'monto' => 1000]);

        $this->actingAs($admin);
        $this->get(route('sedes.show', $sede))->assertOk()->assertSee('ito-facts', false)->assertSee('Banfield 1');
        $this->get(route('bloques.show', $bloque))->assertOk()->assertSee('bloqueTabs', false)->assertSee('María Gómez');
        $this->get(route('alumnos.show', $alumno))->assertOk()->assertSee('alumnoTabs', false)->assertSee('Estado de cuenta')->assertSee('Marzo');
        $this->get(route('profesores.show', $profe))->assertOk()->assertSee('Bloques y rol');
        $this->get(route('eventos.show', $evento))->assertOk()->assertSee('Muestra');
        $this->get(route('shows.show', $show))->assertOk()->assertSee('Plaza');
        $this->get(route('gastos.show', $gasto))->assertOk()->assertSee('500,00');
        $this->get(route('cuotas.show', $cuota))->assertOk()->assertSee('Quiénes la pagan');
        $this->get(route('inventarios.show', $item))->assertOk()->assertSee('BAN-REP-1')->assertSee('Actualizar ahora');
        $this->get(route('ordenes-compra.show', $orden))->assertOk()->assertSee('Parche 12')->assertSee('100,00');
        $this->get(route('pagos.show', $pago))->assertOk()->assertSee('Detalle por alumno')->assertSee('Anular');
        $this->get(route('asistencias.show', $asistencia))->assertOk()->assertSee('Presente');
        $this->get(route('comprobantes-cuota-alumnos.show', $comprobante->id))->assertOk()->assertSee('Aprobar y registrar pago')->assertSee('Cuotas incluidas');
        $this->get(route('personas.show', $maria->persona))->assertOk()->assertSee('personaTabs', false);
        $this->get(route('usuarios.show', $maria))->assertOk()->assertSee('usuarioTabs', false);
    }

    public function test_formularios_con_secciones_y_mismos_campos(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $cuota = Cuota::query()->create(['nombre' => 'Abril', 'año' => (int) now()->year, 'mes' => 4, 'monto' => 1000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true]);
        $this->actingAs($admin);

        $formularios = [
            route('sedes.create') => ['name="nombre"', 'name="liquidacion_porc_docente"'],
            route('sedes.edit', $sede) => ['name="_method" value="PUT"'],
            route('bloques.create') => ['name="tambores[]"', 'name="cantidad_max_alumnos"'],
            route('bloques.edit', $bloque) => ['Días y horarios', 'name="dia_semana"'],
            route('alumnos.create') => ['name="nombre_apellido"', 'name="bloque_ids[]"', 'name="instrumento_principal"'],
            route('profesores.create') => ['name="nombre"', 'name="cuenta_modo"'],
            route('eventos.create') => ['name="titulo"', 'name="tipo_evento"'],
            route('shows.create') => ['name="bloque_ids[]"', 'name="convocatoria_abierta"'],
            route('gastos.create') => ['name="tipo"', 'name="monto"'],
            route('cuotas.create') => ['name="alcance"', 'name="alumno_ids[]"'],
            route('cuotas.edit', $cuota) => ['name="_method" value="PUT"'],
            route('inventarios.create') => ['name="propietario_tipo"', 'name="estado"'],
            route('ordenes-compra.create') => ['name="item_descripcion[]"', 'oc-fila-plantilla'],
            route('pagos.create') => ['name="monto_total"', 'name="liquidar_profesor"'],
            route('personas.create') => ['name="contacto_emergencia_nombre"'],
            route('usuarios.create') => ['name="password_confirmation"'],
            route('facturacion-mensual.create') => ['name="monto_facturado"'],
            route('comprobantes-cuota-alumnos.create') => ['name="bloque_ids[]"', 'name="comprobante"'],
        ];

        foreach ($formularios as $url => $campos) {
            $r = $this->get($url)->assertOk();
            $r->assertSee('ito-form-section', false)->assertSee('ito-form-actions', false);
            foreach ($campos as $campo) {
                $r->assertSee($campo, false);
            }
        }
    }

    public function test_registro_tiene_etiquetas_y_campos(): void
    {
        $html = view('auth.register', ['hasUsernameColumn' => true])
            ->with('errors', new \Illuminate\Support\ViewErrorBag)
            ->render();

        foreach (['for="name"', 'for="username"', 'for="email"', 'for="password"', 'for="password_confirmation"', 'name="password_confirmation"'] as $fragmento) {
            $this->assertStringContainsString($fragmento, $html);
        }
    }
}
