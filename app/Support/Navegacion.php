<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Estructura del menú lateral y de las migas de la barra superior.
 *
 * Solo presentación: cada enlace conserva la misma condición de acceso que
 * tenía el menú anterior (tieneAccesoModulo / can). Se calcula una vez por
 * request y se comparte entre el menú y la barra superior.
 */
class Navegacion
{
    /**
     * @return array{
     *     top: list<array<string, mixed>>,
     *     grupos: list<array{clave: string, label: string, abierto: bool, links: list<array<string, mixed>>}>,
     *     migas: list<array{label: string, href: string|null}>
     * }
     */
    public static function para(User $user, Request $request): array
    {
        $cache = $request->attributes->get('ito.navegacion');
        if (is_array($cache) && ($cache['user'] ?? null) === $user->id) {
            return $cache['data'];
        }

        $data = self::construir($user, $request);
        $request->attributes->set('ito.navegacion', ['user' => $user->id, 'data' => $data]);

        return $data;
    }

    private static function construir(User $u, Request $request): array
    {
        $mod = fn (string $m) => $u->tieneAccesoModulo($m);
        $gestion = $u->puedeGestionarOperativo();

        $profesorLinks = [
            self::link('dashboard', 'Hoy en clase', 'bi-house', 'dashboard'),
            $mod('profesor.asistencia') ? self::link('profesor.asistencias.create', 'Asistencia de hoy', 'bi-check2-square', 'profesor.asistencias.create') : null,
            $mod('profesor.mis_bloques') ? self::link('profesor.bloques', 'Mis bloques', 'bi-collection', 'profesor.bloques*') : null,
            $mod('profesor.asistencia') ? self::link('profesor.asistencias.matrix', 'Planilla del mes', 'bi-grid-3x3', 'profesor.asistencias.matrix*') : null,
            $mod('profesor.mis_alumnos') ? self::link('profesor.alumnos', 'Mis alumnos', 'bi-people', 'profesor.alumnos*') : null,
            $mod('profesor.pagos_cuotas') ? self::link('profesor.pagos-cuotas.index', 'Pagos de cuotas', 'bi-cash-stack', 'profesor.pagos-cuotas.*') : null,
            $mod('comprobantes') ? self::link('comprobantes-cuota-alumnos.create', 'Cargar comprobante', 'bi-upload', 'comprobantes-cuota-alumnos.create') : null,
            $mod('comprobantes') ? self::link('comprobantes-cuota-alumnos.index', 'Comprobantes', 'bi-receipt', 'comprobantes-cuota-alumnos.*') : null,
            $mod('profesor.mis_eventos') ? self::link('profesor.eventos', 'Mis eventos', 'bi-calendar-event', 'profesor.eventos*') : null,
            $mod('programa') ? self::link('programa.index', 'Programa', 'bi-journal-text', 'programa.index') : null,
            $mod('programa') ? self::link('programa.partituras.index', 'Partituras', 'bi-file-earmark-music', 'programa.partituras.*') : null,
            $mod('calendario') ? self::link('calendario.index', 'Calendario', 'bi-calendar3', 'calendario.*') : null,
        ];

        $alumnoLinks = [
            self::link('dashboard', 'Portal familia', 'bi-house', 'dashboard'),
            $mod('programa') ? self::link('programa.index', 'Programa', 'bi-journal-text', 'programa.index') : null,
            $mod('programa') ? self::link('programa.partituras.index', 'Partituras', 'bi-file-earmark-music', 'programa.partituras.*') : null,
            self::link('biblioteca.index', 'Biblioteca', 'bi-images', 'biblioteca.*'),
            self::link('comprobante-cuota-public.create', 'Pagar cuota', 'bi-credit-card', 'comprobante-cuota-public.*'),
            $mod('calendario') ? self::link('calendario.index', 'Calendario', 'bi-calendar3', 'calendario.*') : null,
        ];

        $configEspacio = [
            self::link('apariencia.edit', 'Apariencia', 'bi-palette2', 'apariencia.*'),
            $mod('ayuda') ? self::link('ayuda', 'Ayuda', 'bi-question-circle', 'ayuda') : null,
        ];

        $top = [];
        $grupos = [];

        if ($gestion) {
            $top[] = self::link('dashboard', 'Inicio', 'bi-house', 'dashboard');
            $top[] = self::link('operativo.pendientes', 'Pendientes', 'bi-lightning-charge', 'operativo.pendientes');
            if ($mod('calendario')) {
                $top[] = self::link('calendario.index', 'Calendario', 'bi-calendar3', 'calendario.*');
            }

            if ($u->isProfesor()) {
                $grupos[] = self::grupo('docente', 'Docente', array_filter($profesorLinks, fn ($l) => $l && ! in_array($l['route'], ['dashboard', 'calendario.index'], true)), ['profesor.*']);
            }
            if ($u->isAlumno()) {
                $grupos[] = self::grupo('familia', 'Mi espacio (alumno)', array_filter($alumnoLinks, fn ($l) => $l && ! in_array($l['route'], ['dashboard', 'calendario.index'], true)), ['comprobante-cuota-public.*']);
            }

            $grupos[] = self::grupo('personas', 'Personas', [
                $mod('admin.personas') ? self::link('personas.index', 'Personas', 'bi-person-vcard', 'personas.*') : null,
                $mod('admin.alumnos') ? self::link('alumnos.index', 'Alumnos', 'bi-people', 'alumnos.*') : null,
                $mod('admin.profesores') ? self::link('profesores.index', 'Profesores', 'bi-person-badge', 'profesores.*') : null,
                $u->can('usuarios.view') ? self::link('usuarios.index', 'Usuarios y permisos', 'bi-shield-lock', 'usuarios.*') : null,
            ]);
            $grupos[] = self::grupo('escuela', 'Escuela', [
                $mod('admin.sedes') ? self::link('sedes.index', 'Sedes', 'bi-geo-alt', 'sedes.*') : null,
                $mod('admin.bloques') ? self::link('bloques.index', 'Bloques', 'bi-collection', 'bloques.*') : null,
                $mod('admin.asistencias') ? self::link('asistencias.index', 'Asistencias', 'bi-check2-square', 'asistencias.*') : null,
            ]);
            $grupos[] = self::grupo('actividades', 'Actividades', [
                $mod('admin.eventos') ? self::link('eventos.index', 'Eventos', 'bi-calendar-event', 'eventos.*') : null,
                $mod('admin.shows') ? self::link('shows.index', 'Shows', 'bi-mic', 'shows.*') : null,
            ]);
            if ($mod('admin.villa_gesell')) {
                $grupos[] = self::grupo('villa_gesell', 'Villa Gesell', [
                    self::link('villa-gesell.index', 'Resumen', 'bi-sun', 'villa-gesell.index'),
                    self::link('villa-gesell.inscriptos.index', 'Inscriptos', 'bi-person-lines-fill', 'villa-gesell.inscriptos.*', inactiveQuery: ['estado' => 'sena']),
                    self::link('villa-gesell.inscriptos.index', 'Seña', 'bi-cash-coin', 'villa-gesell.inscriptos.index', query: ['estado' => 'sena'], activeQuery: ['estado' => 'sena']),
                    self::link('villa-gesell.calendario', 'Calendario', 'bi-calendar-week', 'villa-gesell.calendario'),
                    self::link('villa-gesell.insumos.index', 'Insumos', 'bi-basket', 'villa-gesell.insumos.*'),
                    self::link('villa-gesell.gastos.index', 'Gastos', 'bi-wallet2', 'villa-gesell.gastos.*'),
                    self::link('villa-gesell.plan', 'Plan de gastos', 'bi-clipboard-data', 'villa-gesell.plan'),
                ], ['villa-gesell.*']);
            }
            $grupos[] = self::grupo('finanzas', 'Administración', [
                $mod('admin.cuotas') ? self::link('cuotas.index', 'Cuotas', 'bi-cash-stack', 'cuotas.*') : null,
                $mod('admin.pagos') ? self::link('pagos.index', 'Pagos', 'bi-receipt', 'pagos.*') : null,
                $mod('comprobantes') ? self::link('comprobantes-cuota-alumnos.index', 'Comprobantes', 'bi-upload', 'comprobantes-cuota-alumnos.*') : null,
                $mod('admin.facturacion_mensual') ? self::link('facturacion-mensual.index', 'Facturación mensual', 'bi-file-earmark-text', 'facturacion-mensual.*') : null,
                $mod('admin.gastos') ? self::link('gastos.index', 'Gastos', 'bi-wallet2', 'gastos.*') : null,
                $mod('admin.reportes') ? self::link('reportes.index', 'Reportes', 'bi-graph-up', 'reportes.*') : null,
                $u->can('facturacion.view') ? self::link('operativo.cierre-mes', 'Cierre de mes', 'bi-calendar-check', 'operativo.cierre-mes') : null,
            ]);
            $grupos[] = self::grupo('inventario', 'Inventario', [
                $mod('admin.inventarios') ? self::link('inventarios.index', 'Inventarios', 'bi-box-seam', 'inventarios.*') : null,
                $mod('admin.plan_compras') ? self::link('plan-compras.index', 'Plan de compras', 'bi-clipboard-check', 'plan-compras.*') : null,
                $mod('admin.ordenes_compra') ? self::link('ordenes-compra.index', 'Órdenes de compra', 'bi-cart', 'ordenes-compra.*') : null,
            ]);
            $grupos[] = self::grupo('contenido', 'Contenido', [
                $mod('programa') ? self::link('programa.index', 'Programa', 'bi-journal-text', 'programa.index') : null,
                $mod('programa') ? self::link('programa.partituras.index', 'Partituras', 'bi-file-earmark-music', 'programa.partituras.*') : null,
                $mod('admin.disenos') ? self::link('disenos.index', 'Diseño', 'bi-palette', 'disenos.*') : null,
                $u->can('biblioteca.admin') ? self::link('biblioteca.admin.index', 'Biblioteca', 'bi-images', 'biblioteca.admin.*') : null,
            ], ['programa.seccion.*', 'programa.toque.*']);
            $grupos[] = self::grupo('config', 'Configuración', [
                $u->can('auditoria.view') ? self::link('auditoria.index', 'Auditoría', 'bi-clock-history', 'auditoria.*') : null,
                $u->can('usuarios.permissions') ? self::link('accesos.index', 'Visibilidad del menú', 'bi-eye', 'accesos.*') : null,
                self::link('apariencia.edit', 'Apariencia', 'bi-palette2', 'apariencia.*'),
                $mod('ayuda') ? self::link('ayuda', 'Ayuda', 'bi-question-circle', 'ayuda') : null,
            ]);
        } else {
            $esAlumno = $u->isAlumno();
            $links = array_values(array_filter($esAlumno ? $alumnoLinks : $profesorLinks));
            $top[] = array_shift($links);
            if ($u->isProfesor()) {
                $top[] = self::link('operativo.pendientes', 'Pendientes', 'bi-lightning-charge', 'operativo.pendientes');
            }
            $grupos[] = self::grupo($esAlumno ? 'familia' : 'docente', $esAlumno ? 'Familia' : 'Docente', $links);
            $grupos[] = self::grupo('config', 'Configuración', $configEspacio);
        }

        // Resolver URLs y estado activo.
        $top = array_map(fn ($l) => self::resolver($l, $request), $top);
        $migas = [];
        $activo = collect($top)->firstWhere('active', true);
        if ($activo) {
            $migas = [['label' => $activo['label'], 'href' => $activo['href']]];
        }

        $salida = [];
        foreach ($grupos as $g) {
            $links = array_values(array_map(fn ($l) => self::resolver($l, $request), array_values(array_filter($g['links']))));
            if ($links === []) {
                continue;
            }
            $linkActivo = collect($links)->firstWhere('active', true);
            $abierto = $linkActivo !== null;
            foreach ($g['patterns'] as $p) {
                $abierto = $abierto || $request->routeIs($p);
            }
            if ($linkActivo && $migas === []) {
                $migas = [
                    ['label' => $g['label'], 'href' => null],
                    ['label' => $linkActivo['label'], 'href' => $linkActivo['href']],
                ];
            } elseif ($abierto && $migas === []) {
                $migas = [['label' => $g['label'], 'href' => null]];
            }
            $salida[] = ['clave' => $g['clave'], 'label' => $g['label'], 'abierto' => $abierto, 'links' => $links];
        }

        return ['top' => $top, 'grupos' => $salida, 'migas' => $migas];
    }

    private static function link(string $route, string $label, string $icon, string $pattern, array $query = [], array $activeQuery = [], array $inactiveQuery = []): array
    {
        return compact('route', 'label', 'icon', 'pattern', 'query') + ['active_query' => $activeQuery, 'inactive_query' => $inactiveQuery];
    }

    private static function grupo(string $clave, string $label, array $links, array $patterns = []): array
    {
        return ['clave' => $clave, 'label' => $label, 'links' => array_values(array_filter($links)), 'patterns' => $patterns];
    }

    private static function resolver(array $l, Request $request): array
    {
        $activo = $request->routeIs($l['pattern']);
        foreach ($l['active_query'] as $k => $v) {
            $activo = $activo && (string) $request->query($k) === (string) $v;
        }
        foreach ($l['inactive_query'] as $k => $v) {
            $activo = $activo && (string) $request->query($k) !== (string) $v;
        }

        return [
            'label' => $l['label'],
            'icon' => $l['icon'],
            'route' => $l['route'],
            'href' => route($l['route'], $l['query']),
            'active' => $activo,
        ];
    }
}
