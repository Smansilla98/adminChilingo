<?php

namespace App\Support;

/**
 * Guía paso a paso para dirección: qué quiero hacer, dónde está y cómo se hace.
 * Cada tarea enlaza a su pantalla. Si cambia un menú o un botón, se corrige acá.
 */
class GuiaDireccion
{
    /**
     * @return list<array{clave: string, titulo: string, items: list<array{quiero: string, donde: string, ruta: string|null, pasos: list<string>, ojo?: string}>}>
     */
    public static function secciones(): array
    {
        return [
            ['clave' => 'empezar', 'titulo' => 'Para empezar (5 minutos)', 'items' => [
                ['quiero' => 'Saber cómo está la escuela hoy', 'donde' => 'Menú › Inicio', 'ruta' => 'dashboard', 'pasos' => [
                    'Tocá «Inicio» arriba de todo en el menú de la izquierda (en el celular, el menú se abre con el botón ☰).',
                    'Arriba ves los números del día: alumnos, cobros del mes y lo que está pendiente.',
                    'Cada tarjeta se puede tocar para ver el detalle.',
                ]],
                ['quiero' => 'Ver lo que necesita mi atención', 'donde' => 'Menú › Pendientes', 'ruta' => 'operativo.pendientes', 'pasos' => [
                    'Tocá «Pendientes» en el menú.',
                    'Vas a ver los comprobantes por revisar, las asistencias que faltan hoy y las cuotas del mes.',
                    'Tocá cada cosa para resolverla. Cuando no queda nada, dice «Nada por revisar».',
                ], 'ojo' => 'Si entrás una vez por día a «Pendientes», no se te pasa nada.'],
                ['quiero' => 'Encontrar a alguien o algo rápido', 'donde' => 'Arriba › la lupa', 'ruta' => null, 'pasos' => [
                    'Tocá la lupa de arriba (en la computadora también funciona Ctrl + K).',
                    'Escribí un nombre, un DNI, un bloque o el nombre de una pantalla.',
                    'Tocá el resultado para ir directo.',
                ]],
                ['quiero' => 'Ver la letra más grande o con más contraste', 'donde' => 'Arriba a la derecha › tus iniciales', 'ruta' => 'apariencia.edit', 'pasos' => [
                    'Tocá el círculo con tus iniciales, arriba a la derecha.',
                    'Activá «Texto grande» o «Alto contraste». También podés elegir modo claro u oscuro.',
                    'Queda guardado para la próxima vez.',
                ]],
            ]],
            ['clave' => 'plata', 'titulo' => 'Plata: cuotas, pagos y comprobantes', 'items' => [
                ['quiero' => 'Saber cuánto entró y cuánto falta cobrar', 'donde' => 'Menú › Administración › Facturación mensual', 'ruta' => 'facturacion-mensual.index', 'pasos' => [
                    'Abrí «Administración» en el menú y tocá «Facturación mensual».',
                    'Elegí el mes. Ves lo facturado, lo cobrado y lo que falta, por sede.',
                    'Para un informe para compartir, andá a «Reportes» y descargalo en Excel o PDF.',
                ]],
                ['quiero' => 'Aprobar los comprobantes que mandan los alumnos', 'donde' => 'Menú › Administración › Comprobantes', 'ruta' => 'comprobantes-cuota-alumnos.index', 'pasos' => [
                    'Tocá «Comprobantes». Arriba están los «Pendiente de revisión».',
                    'Abrí uno y mirá la foto o el PDF: que el monto y la fecha coincidan con lo que entró al banco.',
                    'Si está bien, tocá «Aprobar y registrar pago»: el pago queda cargado solo.',
                    'Si lo viste pero todavía no lo querés registrar, tocá «Marcar como visto».',
                ], 'ojo' => 'Los alumnos con cuenta lo mandan desde «Pagar mi cuota»; los demás, desde el formulario público.'],
                ['quiero' => 'Cargar la cuota del mes', 'donde' => 'Menú › Administración › Cuotas', 'ruta' => 'cuotas.index', 'pasos' => [
                    'Tocá «Cuotas» y después «Nueva cuota».',
                    'Poné el nombre (por ejemplo «Cuota octubre»), el mes, el año y el monto.',
                    'Elegí a quién aplica: toda la escuela, una sede o un bloque. Guardá.',
                ], 'ojo' => 'Sin la cuota cargada, los alumnos no pueden mandar el comprobante de ese mes.'],
                ['quiero' => 'Registrar un pago en efectivo', 'donde' => 'Menú › Administración › Pagos', 'ruta' => 'pagos.index', 'pasos' => [
                    'Tocá «Pagos» y después «Registrar pago».',
                    'Buscá al alumno, marcá la cuota que paga y el monto.',
                    'Guardá. Si te equivocaste, el pago se anula (no se borra): queda registro de todo.',
                ]],
                ['quiero' => 'Cerrar el mes', 'donde' => 'Menú › Administración › Cierre de mes', 'ruta' => 'operativo.cierre-mes', 'pasos' => [
                    'Tocá «Cierre de mes» y elegí el mes.',
                    'Es una lista de control: asistencias, comprobantes, cuotas y facturación.',
                    'Cada punto en rojo tiene un botón que te lleva a resolverlo. Cuando están todos en verde, el mes está cerrado.',
                ]],
                ['quiero' => 'Ver los gastos', 'donde' => 'Menú › Administración › Gastos', 'ruta' => 'gastos.index', 'pasos' => [
                    'Tocá «Gastos». Podés filtrar por sede y por mes.',
                    'Los gastos que cargan las sedes pueden quedar «pendientes de aprobación»: abrilos y aprobalos.',
                ]],
            ]],
            ['clave' => 'personas', 'titulo' => 'Personas: alumnos, profes y cuentas', 'items' => [
                ['quiero' => 'Ver o sumar alumnos', 'donde' => 'Menú › Personas › Alumnos', 'ruta' => 'alumnos.index', 'pasos' => [
                    'Tocá «Alumnos». Buscá por nombre o DNI, o filtrá por sede y bloque.',
                    'Abrí un alumno para ver sus datos, sus bloques, su asistencia y su estado de cuenta.',
                    'Para sumar uno, tocá «Nuevo alumno». Cargá bien el DNI y la fecha de nacimiento: con eso el alumno después crea su cuenta solo.',
                ]],
                ['quiero' => 'Ver profesores y sus bloques', 'donde' => 'Menú › Personas › Profesores y Escuela › Bloques', 'ruta' => 'bloques.index', 'pasos' => [
                    'En «Profesores» ves a cada profe y los bloques donde da clase.',
                    'En «Bloques» ves cada grupo: sede, año, horarios, profes y alumnos.',
                    'Para cambiar el profe o el horario de un bloque, abrilo y tocá «Editar».',
                ]],
                ['quiero' => 'Ver la asistencia', 'donde' => 'Menú › Escuela › Asistencias', 'ruta' => 'asistencias.index', 'pasos' => [
                    'Tocá «Asistencias». Elegí el bloque y el mes: ves la planilla completa.',
                    'P = presente, T = llegó tarde, J = faltó con aviso, I = faltó sin aviso.',
                ]],
                ['quiero' => 'Darle una cuenta a alguien o cambiar qué puede hacer', 'donde' => 'Menú › Personas › Usuarios y permisos', 'ruta' => 'usuarios.index', 'pasos' => [
                    'Tocá «Usuarios y permisos» y después «Nueva cuenta».',
                    'Elegí la persona (o creala), un usuario y una contraseña.',
                    'Asignale un rol (por ejemplo «Coordinador» de una sede). Los profes y alumnos reciben su rol solos, por estar en un bloque.',
                ], 'ojo' => 'Para entender qué hace cada rol, mirá «Configuración › Perfiles y roles». Los alumnos crean su cuenta solos en la página «Crear mi cuenta de alumno», con su DNI.'],
            ]],
            ['clave' => 'escuela', 'titulo' => 'La escuela: sedes, programa y contenido', 'items' => [
                ['quiero' => 'Ver o editar una sede (y ubicarla en el mapa)', 'donde' => 'Menú › Escuela › Sedes', 'ruta' => 'sedes.index', 'pasos' => [
                    'Tocá «Sedes» y abrí una.',
                    'Tocá «Editar». En «Ubicación en el mapa» hacé clic donde está la puerta, o tocá «Buscar dirección».',
                    'Activá «Mostrar en el mapa público de sedes» y guardá.',
                ]],
                ['quiero' => 'Cambiar el año de un toque, renombrarlo o sacarlo del programa', 'donde' => 'Programa › Gestionar toques', 'ruta' => 'programa.gestion', 'pasos' => [
                    'Abrí «Contenido › Programa» y tocá «Gestionar toques».',
                    'En cada toque podés cambiar el año, el nombre, si se sigue tocando y si está en el programa.',
                    'Tocá «Guardar» en la fila que cambiaste. El nombre viejo queda guardado.',
                ], 'ojo' => 'Los alumnos ven el material de los toques hasta el año de su bloque. Si cambiás un toque de año, cambia quién lo ve.'],
                ['quiero' => 'Ver la discografía y el mapa de sedes', 'donde' => 'Programa › Discografía y Sedes', 'ruta' => 'programa.discos.index', 'pasos' => [
                    'La discografía muestra los cinco discos de la banda con sus temas. Abrí un disco y tocá «Editar» para corregir datos o subir la tapa.',
                    'El mapa de sedes muestra las sedes ubicadas y el botón «Cómo llegar».',
                ]],
                ['quiero' => 'Hacer un flyer o una pieza para redes', 'donde' => 'Menú › Contenido › Diseño', 'ruta' => 'disenos.index', 'pasos' => [
                    'Tocá «Diseño» y elegí el formato (historia, post, afiche…).',
                    'En la pestaña «Marca» están los logos y el kit de la escuela.',
                    'Descargá la pieza terminada.',
                ]],
                ['quiero' => 'Organizar un evento o un show', 'donde' => 'Menú › Actividades', 'ruta' => 'eventos.index', 'pasos' => [
                    'Tocá «Eventos» (o «Shows») y después «Nuevo evento».',
                    'Poné fecha, lugar y a quién está dirigido (toda la escuela, una sede o un bloque).',
                    'Aparece en el calendario de quienes corresponda.',
                ]],
                ['quiero' => 'Ver el inventario de instrumentos', 'donde' => 'Menú › Inventario', 'ruta' => 'inventarios.index', 'pasos' => [
                    'Tocá «Inventarios». Filtrá por sede o buscá un instrumento.',
                    'Desde «Plan de compras» y «Órdenes de compra» se organiza lo que hay que comprar.',
                ]],
            ]],
            ['clave' => 'control', 'titulo' => 'Control y ayuda', 'items' => [
                ['quiero' => 'Saber quién cambió algo', 'donde' => 'Menú › Configuración › Auditoría', 'ruta' => 'auditoria.index', 'pasos' => [
                    'Tocá «Auditoría». Cada cambio importante queda anotado: quién, cuándo y qué cambió.',
                    'Filtrá por tipo de acción (alta, cambio, baja) o por qué se modificó (un alumno, un pago, una cuota…).',
                ]],
                ['quiero' => 'Entender un mensaje de error', 'donde' => 'Cualquier pantalla', 'ruta' => null, 'pasos' => [
                    'Los mensajes en rojo dicen qué falta o qué está mal (por ejemplo «Falta completar la fecha»). Corregilo y volvé a guardar.',
                    '«No tenés acceso» significa que tu cuenta no tiene ese permiso: se cambia en «Usuarios y permisos».',
                    'Si algo sigue sin andar, sacale una captura a la pantalla y mandásela a quien administra el sistema.',
                ]],
            ]],
        ];
    }
}
