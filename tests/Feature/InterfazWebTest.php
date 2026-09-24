<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AvisoNotification;
use App\Support\AparienciaTema;
use App\Support\Navegacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Capa de interfaz del panel: menú por permisos, migas, campana de avisos,
 * tema claro/oscuro y páginas de error propias.
 */
class InterfazWebTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function menu(User $user, string $ruta): array
    {
        $request = Request::create(route($ruta));
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return Navegacion::para($user, $request);
    }

    public function test_el_menu_respeta_los_permisos_y_arma_las_migas(): void
    {
        $contador = $this->usuario('Contadora');
        $this->asignarRol($contador->persona, 'contador');

        $nav = $this->menu($contador->fresh(), 'pagos.index');
        $rutas = collect($nav['grupos'])->flatMap(fn ($g) => collect($g['links'])->pluck('route'));

        $this->assertTrue($rutas->contains('pagos.index'));
        $this->assertFalse($rutas->contains('alumnos.index'));
        $this->assertFalse($rutas->contains('usuarios.index'));
        $this->assertSame(['Administración', 'Pagos'], collect($nav['migas'])->pluck('label')->all());

        $grupo = collect($nav['grupos'])->firstWhere('clave', 'finanzas');
        $this->assertTrue($grupo['abierto']);
        $this->assertTrue(collect($grupo['links'])->firstWhere('route', 'pagos.index')['active']);
    }

    public function test_el_admin_ve_todos_los_grupos_y_el_docente_su_espacio(): void
    {
        $grupos = collect($this->menu($this->admin(), 'dashboard')['grupos'])->pluck('clave');
        foreach (['personas', 'escuela', 'actividades', 'finanzas', 'inventario', 'contenido', 'config'] as $clave) {
            $this->assertTrue($grupos->contains($clave), "Falta el grupo {$clave}");
        }

        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $this->bloque($this->sede('Palomar')));
        $nav = $this->menu($profe->fresh(), 'dashboard');
        $this->assertSame('Hoy en clase', $nav['top'][0]['label']);
        $this->assertSame(['docente', 'config'], collect($nav['grupos'])->pluck('clave')->all());
    }

    public function test_el_layout_muestra_migas_campana_y_menu_de_usuario(): void
    {
        $admin = $this->admin();
        $admin->notify(new AvisoNotification(['tipo' => 'pago', 'titulo' => 'Pago registrado', 'mensaje' => 'Cuota de marzo']));

        $this->actingAs($admin)->get(route('alumnos.index'))->assertOk()
            ->assertSee('topbar-crumbs', false)
            ->assertSee('Pago registrado')
            ->assertSee('Avisos: 1 sin leer')
            ->assertSee('Cerrar sesión')
            ->assertSee('data-bs-theme', false);
    }

    public function test_los_avisos_se_marcan_como_leidos_solo_por_su_duenio(): void
    {
        $ana = $this->usuario('Ana');
        $beto = $this->usuario('Beto');
        $ana->notify(new AvisoNotification(['tipo' => 'aviso', 'titulo' => 'Hola', 'mensaje' => 'x', 'enlace' => '/pendientes']));
        $ana->notify(new AvisoNotification(['tipo' => 'aviso', 'titulo' => 'Afuera', 'mensaje' => 'x', 'enlace' => 'https://otro-sitio.test/robo']));
        [$interno, $externo] = [$ana->notifications()->where('data->titulo', 'Hola')->first(), $ana->notifications()->where('data->titulo', 'Afuera')->first()];

        $this->actingAs($beto)->post(route('notificaciones.leer', $interno->id))->assertNotFound();

        $this->actingAs($ana)->post(route('notificaciones.leer', $interno->id))->assertRedirect('/pendientes');
        $this->actingAs($ana)->from('/dashboard')->post(route('notificaciones.leer', $externo->id))->assertRedirect('/dashboard');
        $this->assertSame(0, $ana->fresh()->unreadNotifications()->count());

        $ana->notify(new AvisoNotification(['tipo' => 'aviso', 'titulo' => 'Otro', 'mensaje' => 'x']));
        $this->actingAs($ana)->from('/dashboard')->post(route('notificaciones.leer-todas'))->assertRedirect('/dashboard');
        $this->assertSame(0, $ana->fresh()->unreadNotifications()->count());
    }

    public function test_el_tema_se_guarda_sin_perder_el_resto_de_la_apariencia(): void
    {
        $user = $this->usuario('Ana');
        $user->forceFill(['apariencia_json' => ['accent' => '#3ec8ea', 'font_display' => 'Sora', 'font_body' => 'Inter']])->save();

        $this->actingAs($user)->post(route('apariencia.tema'), ['tema' => 'oscuro'])->assertRedirect();
        $this->assertSame('oscuro', $user->fresh()->apariencia_json['tema']);
        $this->assertSame('#3ec8ea', $user->fresh()->apariencia_json['accent']);
        $this->assertSame('oscuro', AparienciaTema::temaDe($user->fresh()));

        $this->actingAs($user)->post(route('apariencia.tema'), ['tema' => 'violeta'])->assertSessionHasErrors('tema');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('"oscuro"', false);
    }

    public function test_el_acento_personalizado_mantiene_contraste_accesible(): void
    {
        foreach (['#f26422', '#3ec8ea', '#ffe600', '#3daf3a'] as $hex) {
            $boton = AparienciaTema::ajustarContraste($hex, '#ffffff', 4.5, -1);
            $this->assertGreaterThanOrEqual(4.5, AparienciaTema::contraste($boton, '#ffffff'), $hex);
            $oscuro = AparienciaTema::ajustarContraste($hex, '#171a20', 4.5, 1);
            $this->assertGreaterThanOrEqual(4.5, AparienciaTema::contraste($oscuro, '#171a20'), $hex);
        }
    }

    public function test_tablero_con_alcance_por_sede_y_sin_funciones_no_falla(): void
    {
        $quilmes = $this->sede('Quilmes');
        $this->bloque($quilmes)->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']);
        $coord = $this->usuario('Coordinadora');
        $this->rolDocenteEnSede($coord->persona, $quilmes, 'coordinador');

        $this->actingAs($coord->fresh())->get(route('dashboard'))->assertOk();
        $this->actingAs($this->usuario('Sin funciones'))->get(route('dashboard'))->assertOk();
    }

    public function test_paginas_de_error_con_marca(): void
    {
        $this->get('/no-existe-esta-ruta')->assertNotFound()->assertSee('No encontramos esta página');

        $sinPermiso = $this->usuario('Sin permiso');
        $this->actingAs($sinPermiso)->get(route('usuarios.index'))->assertForbidden()->assertSee('No tenés acceso a esta sección');
    }

    public function test_login_tiene_etiquetas_visibles_y_mismos_campos(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('<label class="auth-field-label" for="username">', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false);
    }
}
