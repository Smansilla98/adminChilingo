<?php

/*
|--------------------------------------------------------------------------
| Catálogo de permisos y roles
|--------------------------------------------------------------------------
|
| Fuente de verdad de los permisos granulares y de los permisos que trae
| cada rol. `php artisan chilinga:permisos:sync` (y la migración de
| plataforma) vuelcan este catálogo a las tablas de Spatie.
|
| Los roles no dan acceso "global" por sí mismos: el alcance lo define la
| asignación (global / sede / bloque) o la relación académica de la que
| se deriva el rol. Ver docs/ROLES_Y_PERMISOS.md.
|
*/

return [

    'grupos' => [
        'Personas' => [
            'personas.view' => 'Ver personas',
            'personas.create' => 'Crear personas',
            'personas.update' => 'Editar personas',
            'personas.delete' => 'Dar de baja personas',
            'personas.merge' => 'Fusionar personas duplicadas',
        ],
        'Alumnos' => [
            'alumnos.view' => 'Ver alumnos',
            'alumnos.create' => 'Crear alumnos',
            'alumnos.update' => 'Editar alumnos',
            'alumnos.delete' => 'Eliminar alumnos',
            'alumnos.import' => 'Importar alumnos',
            'alumnos.export' => 'Exportar alumnos',
        ],
        'Profesores' => [
            'profesores.view' => 'Ver profesores',
            'profesores.create' => 'Crear profesores',
            'profesores.update' => 'Editar profesores',
            'profesores.delete' => 'Eliminar profesores',
        ],
        'Sedes' => [
            'sedes.view' => 'Ver sedes',
            'sedes.manage' => 'Crear y editar sedes',
            'sedes.delete' => 'Eliminar sedes',
        ],
        'Bloques' => [
            'bloques.view' => 'Ver bloques',
            'bloques.manage' => 'Crear y editar bloques',
            'bloques.delete' => 'Eliminar bloques',
        ],
        'Asistencias' => [
            'asistencias.view' => 'Ver asistencias',
            'asistencias.create' => 'Tomar asistencia',
            'asistencias.update' => 'Corregir asistencias',
            'asistencias.delete' => 'Eliminar asistencias',
        ],
        'Seguimiento pedagógico' => [
            'seguimiento.view' => 'Ver cuaderno pedagógico',
            'seguimiento.create' => 'Escribir observaciones',
        ],
        'Cuotas' => [
            'cuotas.view' => 'Ver cuotas',
            'cuotas.create' => 'Crear cuotas',
            'cuotas.update' => 'Editar cuotas',
            'cuotas.delete' => 'Eliminar cuotas',
        ],
        'Becas' => [
            'becas.view' => 'Ver becas',
            'becas.manage' => 'Otorgar y editar becas',
        ],
        'Pagos' => [
            'pagos.view' => 'Ver pagos',
            'pagos.create' => 'Registrar pagos',
            'pagos.update' => 'Editar pagos',
            'pagos.reverse' => 'Anular pagos',
            'comprobantes.view' => 'Ver comprobantes enviados por alumnos',
            'comprobantes.create' => 'Cargar comprobantes de alumnos',
            'comprobantes.approve' => 'Aprobar comprobantes (genera pago)',
        ],
        'Facturación' => [
            'facturacion.view' => 'Ver facturación',
            'facturacion.manage' => 'Cargar facturación mensual',
        ],
        'Gastos' => [
            'gastos.view' => 'Ver gastos',
            'gastos.create' => 'Registrar gastos',
            'gastos.update' => 'Editar gastos',
            'gastos.delete' => 'Eliminar gastos',
            'gastos.approve' => 'Aprobar gastos',
        ],
        'Inventario' => [
            'inventario.view' => 'Ver inventario',
            'inventario.create' => 'Cargar ítems',
            'inventario.update' => 'Editar ítems y registrar movimientos',
            'inventario.delete' => 'Eliminar ítems',
        ],
        'Compras' => [
            'compras.view' => 'Ver plan y órdenes de compra',
            'compras.create' => 'Crear órdenes de compra',
            'compras.approve' => 'Aprobar órdenes de compra',
        ],
        'Eventos' => [
            'eventos.view' => 'Ver eventos',
            'eventos.create' => 'Crear eventos',
            'eventos.update' => 'Editar eventos',
            'eventos.delete' => 'Eliminar eventos',
            'shows.view' => 'Ver shows',
            'shows.manage' => 'Gestionar shows',
            'calendario.view' => 'Ver calendario',
            'villa_gesell.manage' => 'Gestionar gira Villa Gesell',
        ],
        'Programa y partituras' => [
            'partituras.view' => 'Ver partituras',
            'partituras.admin' => 'Administrar programa y partituras',
            'biblioteca.admin' => 'Moderar biblioteca',
            'disenos.manage' => 'Usar el módulo Diseño',
        ],
        'Reportes' => [
            'reportes.view' => 'Ver reportes',
            'auditoria.view' => 'Ver auditoría',
        ],
        'Notificaciones' => [
            'notificaciones.send' => 'Enviar recordatorios y avisos',
        ],
        'Usuarios' => [
            'usuarios.view' => 'Ver usuarios',
            'usuarios.create' => 'Crear usuarios',
            'usuarios.update' => 'Editar, activar y resetear usuarios',
            'usuarios.permissions' => 'Asignar roles y permisos',
            'usuarios.assign_admin' => 'Asignar roles de administración',
        ],
    ],

    /*
    | Roles. `ambitos`: dónde tiene sentido asignarlo. `derivado`: solo surge de
    | datos académicos (no se asigna a mano). `permisos`: lista o ['*'].
    */
    'roles' => [
        'superadministrador' => [
            'nombre' => 'Superadministrador',
            'descripcion' => 'Acceso total, incluida la gestión de administradores.',
            'ambitos' => ['global'],
            'permisos' => ['*'],
        ],
        'administrador' => [
            'nombre' => 'Administrador',
            'descripcion' => 'Dirección: gestiona toda la escuela.',
            'ambitos' => ['global', 'sede'],
            'permisos' => ['*', '!usuarios.assign_admin'],
        ],
        'coordinador' => [
            'nombre' => 'Coordinador',
            'descripcion' => 'Coordina una sede: alumnos, bloques, asistencia, eventos y reportes.',
            'ambitos' => ['sede', 'global'],
            'permisos' => [
                'personas.view', 'personas.create', 'personas.update',
                'alumnos.view', 'alumnos.create', 'alumnos.update', 'alumnos.delete', 'alumnos.export',
                'profesores.view',
                'sedes.view', 'sedes.manage',
                'bloques.view', 'bloques.manage', 'bloques.delete',
                'asistencias.view', 'asistencias.create', 'asistencias.update', 'asistencias.delete',
                'seguimiento.view', 'seguimiento.create',
                'comprobantes.view', 'comprobantes.create',
                'eventos.view', 'eventos.create', 'eventos.update', 'eventos.delete',
                'shows.view', 'shows.manage', 'calendario.view', 'villa_gesell.manage',
                'partituras.view', 'reportes.view', 'inventario.view', 'becas.view',
            ],
        ],
        'coordinador_area' => [
            'nombre' => 'Coordinador de área',
            'descripcion' => 'Coordina un área (género, costa, tambores) en las sedes donde enseña.',
            'ambitos' => ['sede', 'global'],
            'permisos' => [
                'personas.view', 'alumnos.view', 'alumnos.create', 'alumnos.update', 'alumnos.export',
                'asistencias.view', 'asistencias.create', 'asistencias.update', 'asistencias.delete',
                'seguimiento.view', 'seguimiento.create',
                'calendario.view', 'eventos.view', 'partituras.view', 'bloques.view',
            ],
        ],
        'profesor' => [
            'nombre' => 'Profesor',
            'descripcion' => 'Da clase: ve sus bloques y alumnos, toma asistencia.',
            'ambitos' => ['bloque', 'sede'],
            'permisos' => [
                'alumnos.view', 'bloques.view',
                'asistencias.view', 'asistencias.create', 'asistencias.update',
                'seguimiento.view', 'seguimiento.create',
                'comprobantes.view', 'comprobantes.create', 'pagos.view',
                'eventos.view', 'calendario.view', 'partituras.view',
            ],
        ],
        'alumno' => [
            'nombre' => 'Alumno',
            'descripcion' => 'Inscripto en un bloque. Ve su propia información.',
            'ambitos' => ['bloque'],
            'derivado' => true,
            'permisos' => ['eventos.view', 'calendario.view', 'partituras.view'],
        ],
        'becado' => [
            'nombre' => 'Becado',
            'descripcion' => 'Tiene una beca activa (se deriva de la beca).',
            'ambitos' => ['global'],
            'derivado' => true,
            'permisos' => [],
        ],
        'encargado' => [
            'nombre' => 'Encargado',
            'descripcion' => 'Encargado de sede: inventario, compras y agenda de la sede.',
            'ambitos' => ['sede'],
            'permisos' => [
                'sedes.view', 'bloques.view', 'eventos.view', 'calendario.view',
                'inventario.view', 'inventario.create', 'inventario.update',
                'compras.view', 'compras.create',
            ],
        ],
        'responsable_de_sede' => [
            'nombre' => 'Responsable de sede',
            'descripcion' => 'Administra la operación de una sede (edificio, gastos, inventario).',
            'ambitos' => ['sede'],
            'permisos' => [
                'sedes.view', 'sedes.manage', 'bloques.view', 'alumnos.view', 'personas.view',
                'eventos.view', 'eventos.create', 'eventos.update', 'calendario.view',
                'inventario.view', 'inventario.create', 'inventario.update',
                'gastos.view', 'gastos.create', 'compras.view', 'compras.create',
            ],
        ],
        'responsable_de_inventario' => [
            'nombre' => 'Responsable de inventario',
            'descripcion' => 'Gestiona el inventario completo del ámbito asignado.',
            'ambitos' => ['sede', 'global'],
            'permisos' => [
                'sedes.view', 'inventario.view', 'inventario.create', 'inventario.update', 'inventario.delete',
                'compras.view', 'compras.create',
            ],
        ],
        'administrativo' => [
            'nombre' => 'Administrativo',
            'descripcion' => 'Secretaría: altas de alumnos, cobranzas y agenda.',
            'ambitos' => ['global', 'sede'],
            'permisos' => [
                'personas.view', 'personas.create', 'personas.update',
                'alumnos.view', 'alumnos.create', 'alumnos.update', 'alumnos.export',
                'profesores.view', 'sedes.view', 'bloques.view',
                'cuotas.view', 'becas.view', 'pagos.view', 'pagos.create',
                'comprobantes.view', 'comprobantes.create', 'comprobantes.approve',
                'eventos.view', 'eventos.create', 'eventos.update', 'calendario.view',
                'notificaciones.send',
            ],
        ],
        'contador' => [
            'nombre' => 'Contador',
            'descripcion' => 'Consulta toda la información financiera.',
            'ambitos' => ['global', 'sede'],
            'permisos' => [
                'cuotas.view', 'becas.view', 'pagos.view', 'comprobantes.view',
                'facturacion.view', 'gastos.view', 'compras.view', 'reportes.view', 'sedes.view',
            ],
        ],
        'tesorero' => [
            'nombre' => 'Tesorero',
            'descripcion' => 'Gestiona cuotas, pagos, gastos y facturación.',
            'ambitos' => ['global', 'sede'],
            'permisos' => [
                'alumnos.view', 'personas.view', 'sedes.view', 'bloques.view',
                'cuotas.view', 'cuotas.create', 'cuotas.update', 'cuotas.delete',
                'becas.view', 'becas.manage',
                'pagos.view', 'pagos.create', 'pagos.update', 'pagos.reverse',
                'comprobantes.view', 'comprobantes.create', 'comprobantes.approve',
                'facturacion.view', 'facturacion.manage',
                'gastos.view', 'gastos.create', 'gastos.update', 'gastos.approve',
                'compras.view', 'compras.approve', 'reportes.view', 'notificaciones.send',
            ],
        ],
    ],

    /*
    | Roles Spatie heredados → rol del catálogo. Solo los globales por naturaleza
    | (dirección) otorgan alcance global; los académicos se derivan de datos.
    */
    'legacy' => [
        'admin' => 'administrador',
        'direccion' => 'administrador',
    ],

    /*
    | Módulos de navegación (web y app). Un módulo es visible si la persona
    | tiene alguno de los permisos listados.
    */
    'modulos' => [
        'mi_espacio' => ['etiqueta' => 'Mi espacio', 'icono' => 'person', 'permisos' => ['@alumno']],
        'asistencia' => ['etiqueta' => 'Tomar asistencia', 'icono' => 'checklist', 'permisos' => ['asistencias.create']],
        'bloques' => ['etiqueta' => 'Bloques', 'icono' => 'groups', 'permisos' => ['bloques.view']],
        'alumnos' => ['etiqueta' => 'Alumnos', 'icono' => 'school', 'permisos' => ['alumnos.view']],
        'personas' => ['etiqueta' => 'Personas', 'icono' => 'badge', 'permisos' => ['personas.view']],
        'profesores' => ['etiqueta' => 'Profesores', 'icono' => 'co_present', 'permisos' => ['profesores.view']],
        'sedes' => ['etiqueta' => 'Sedes', 'icono' => 'location_on', 'permisos' => ['sedes.view']],
        'calendario' => ['etiqueta' => 'Calendario', 'icono' => 'calendar_month', 'permisos' => ['calendario.view']],
        'eventos' => ['etiqueta' => 'Eventos', 'icono' => 'celebration', 'permisos' => ['eventos.view']],
        'partituras' => ['etiqueta' => 'Partituras', 'icono' => 'music_note', 'permisos' => ['partituras.view']],
        'cuotas' => ['etiqueta' => 'Cuotas', 'icono' => 'receipt_long', 'permisos' => ['cuotas.view']],
        'pagos' => ['etiqueta' => 'Pagos', 'icono' => 'payments', 'permisos' => ['pagos.view']],
        'facturacion' => ['etiqueta' => 'Facturación', 'icono' => 'request_quote', 'permisos' => ['facturacion.view']],
        'gastos' => ['etiqueta' => 'Gastos', 'icono' => 'account_balance_wallet', 'permisos' => ['gastos.view']],
        'reportes' => ['etiqueta' => 'Reportes', 'icono' => 'bar_chart', 'permisos' => ['reportes.view']],
        'inventario' => ['etiqueta' => 'Inventario', 'icono' => 'inventory_2', 'permisos' => ['inventario.view']],
        'compras' => ['etiqueta' => 'Compras', 'icono' => 'shopping_cart', 'permisos' => ['compras.view']],
        'usuarios' => ['etiqueta' => 'Usuarios y permisos', 'icono' => 'admin_panel_settings', 'permisos' => ['usuarios.view']],
        'notificaciones' => ['etiqueta' => 'Avisos', 'icono' => 'notifications', 'permisos' => []],
    ],
];
