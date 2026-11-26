<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Agrupa los accesos del inicio en módulos.
 * El tablero muestra una card por módulo; el listado completo vive en el panel de cada uno.
 * Ningún acceso de Navegacion::secciones se descarta: lo que no entra en un módulo cae en «Más accesos».
 */
class ModulosInicio
{
    /**
     * @var array<string, array{titulo: string, resumen: string, icono: string, rutas: list<string>, prefijos: list<string>, accion: array{0: string, 1: string}|null}>
     */
    private const DEFINICIONES = [
        'asistencias' => [
            'titulo' => 'Asistencias y bloques',
            'resumen' => 'Pasar lista y ver cómo vienen las clases de la semana.',
            'icono' => 'bi-check2-square',
            'rutas' => [
                'asistencias.index', 'bloques.index', 'progreso.index',
                'profesor.asistencias.create', 'profesor.asistencias.matrix', 'profesor.bloques',
            ],
            'prefijos' => [],
            'accion' => ['asistencias.index', 'Pasar lista'],
        ],
        'personas' => [
            'titulo' => 'Personas y alumnos',
            'resumen' => 'Fichas, altas y quiénes dan clase.',
            'icono' => 'bi-people',
            'rutas' => [
                'personas.index', 'alumnos.index', 'preinscripciones.index', 'profesores.index',
                'usuarios.index', 'profesor.alumnos', 'configuracion.cuentas.edit',
            ],
            'prefijos' => [],
            'accion' => ['alumnos.index', 'Ver alumnos'],
        ],
        'pagos' => [
            'titulo' => 'Pagos y cuotas',
            'resumen' => 'Cobros, comprobantes y lo que falta cobrar.',
            'icono' => 'bi-cash-stack',
            'rutas' => [
                'cuotas.index', 'pagos.index', 'morosos.index', 'cuotas.lote.create',
                'comprobantes-cuota-alumnos.index', 'comprobantes-cuota-alumnos.create',
                'facturacion-mensual.index', 'gastos.index', 'liquidaciones.index', 'liquidaciones.mias',
                'reportes.index', 'direccion.index', 'operativo.cierre-mes',
                'profesor.pagos-cuotas.index', 'alumno.cuota',
            ],
            'prefijos' => [],
            'accion' => ['pagos.index', 'Ver pagos'],
        ],
        'escuela' => [
            'titulo' => 'Escuela y sedes',
            'resumen' => 'Dónde se cursa y cómo está repartido el plantel.',
            'icono' => 'bi-geo-alt',
            'rutas' => ['sedes.index'],
            'prefijos' => [],
            'accion' => ['sedes.index', 'Ver sedes'],
        ],
        'actividades' => [
            'titulo' => 'Actividades y eventos',
            'resumen' => 'Agenda, shows, avisos y la gira.',
            'icono' => 'bi-calendar-event',
            'rutas' => ['eventos.index', 'shows.index', 'difusion.index', 'profesor.eventos', 'alumno.shows'],
            'prefijos' => ['villa-gesell.'],
            'accion' => ['eventos.index', 'Ver agenda'],
        ],
        'recursos' => [
            'titulo' => 'Recursos y programa',
            'resumen' => 'Programa, partituras, biblioteca y archivo.',
            'icono' => 'bi-journal-text',
            'rutas' => [
                'programa.index', 'programa.partituras.index', 'disenos.index',
                'biblioteca.admin.index', 'biblioteca.index',
                'archivo.gestion.tablero', 'archivo.aportes.index', 'archivo.index',
            ],
            'prefijos' => ['programa.', 'biblioteca.', 'archivo.'],
            'accion' => ['programa.index', 'Abrir programa'],
        ],
        'inventario' => [
            'titulo' => 'Inventario y compras',
            'resumen' => 'Stock, plan de compras y órdenes.',
            'icono' => 'bi-box-seam',
            'rutas' => ['inventarios.index', 'plan-compras.index', 'ordenes-compra.index'],
            'prefijos' => [],
            'accion' => ['inventarios.index', 'Ver inventario'],
        ],
        'config' => [
            'titulo' => 'Configuración',
            'resumen' => 'Avisos, apariencia, auditoría y ayuda.',
            'icono' => 'bi-gear',
            'rutas' => [
                'estado-sistema', 'configuracion.avisos.edit', 'auditoria.index',
                'apariencia.edit', 'roles', 'ayuda', 'ayuda.direccion',
            ],
            'prefijos' => [],
            'accion' => ['ayuda', 'Abrir ayuda'],
        ],
    ];

    /**
     * @return list<array{
     *     clave: string,
     *     titulo: string,
     *     resumen: string,
     *     icono: string,
     *     accion: array{href: string, label: string}|null,
     *     links: list<array<string, mixed>>
     * }>
     */
    /** Un módulo por clave, con el acceso del usuario; null si no tiene ninguna pantalla visible. */
    public static function uno(User $user, Request $request, string $clave): ?array
    {
        foreach (self::para($user, $request) as $modulo) {
            if ($modulo['clave'] === $clave) {
                return $modulo;
            }
        }

        return null;
    }

    public static function para(User $user, Request $request): array
    {
        $links = [];
        foreach (Navegacion::secciones($user, $request) as $seccion) {
            foreach ($seccion['links'] as $link) {
                $links[] = $link;
            }
        }

        $usados = [];
        $modulos = [];

        foreach (self::DEFINICIONES as $clave => $def) {
            $delModulo = [];
            foreach ($links as $i => $link) {
                if (isset($usados[$i]) || ! self::pertenece($link['route'] ?? '', $def)) {
                    continue;
                }
                $delModulo[] = $link;
                $usados[$i] = true;
            }
            if ($delModulo === []) {
                continue;
            }
            $modulos[] = self::armar($clave, $def['titulo'], $def['resumen'], $def['icono'], $def['accion'], $delModulo);
        }

        $resto = [];
        foreach ($links as $i => $link) {
            if (! isset($usados[$i])) {
                $resto[] = $link;
            }
        }
        if ($resto !== []) {
            $modulos[] = self::armar(
                'otros',
                'Más accesos',
                'El resto de las pantallas de tu perfil.',
                'bi-grid',
                null,
                $resto,
            );
        }

        return $modulos;
    }

    /**
     * @param  array{rutas: list<string>, prefijos: list<string>}  $def
     */
    private static function pertenece(string $ruta, array $def): bool
    {
        if (in_array($ruta, $def['rutas'], true)) {
            return true;
        }
        foreach ($def['prefijos'] as $prefijo) {
            if (str_starts_with($ruta, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: string, 1: string}|null  $accionPreferida
     * @param  list<array<string, mixed>>  $links
     * @return array{clave: string, titulo: string, resumen: string, icono: string, accion: array{href: string, label: string}|null, links: list<array<string, mixed>>}
     */
    private static function armar(string $clave, string $titulo, string $resumen, string $icono, ?array $accionPreferida, array $links): array
    {
        $accion = null;
        if ($accionPreferida !== null) {
            foreach ($links as $link) {
                if (($link['route'] ?? '') === $accionPreferida[0]) {
                    $accion = ['href' => $link['href'], 'label' => $accionPreferida[1]];
                    break;
                }
            }
        }
        if ($accion === null && isset($links[0]['href'])) {
            $accion = ['href' => $links[0]['href'], 'label' => $links[0]['label']];
        }

        return compact('clave', 'titulo', 'resumen', 'icono', 'accion', 'links');
    }
}
