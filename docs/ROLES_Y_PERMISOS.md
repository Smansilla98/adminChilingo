# Roles y permisos

> Fuente de verdad: [`config/permisos.php`](../config/permisos.php). Este documento se generó
> a partir de ese archivo. Si cambiás el catálogo, corré `php artisan chilinga:permisos:sync`.

## Cómo se decide qué puede hacer alguien

```
permiso efectivo = (permisos de cada rol que ejerce) ∪ (permisos sueltos asignados)
                   cada uno con su alcance: global · sede · bloque
```

1. **Roles derivados de los datos** (no se cargan a mano):

   | Si la persona… | ejerce… | en… |
   |----------------|---------|-----|
   | está inscripta en un bloque | `alumno` | ese bloque |
   | da clase en un bloque (titular o equipo) | `profesor` | ese bloque |
   | figura como coordinadora en `profesor_sede` o `sedes.coordinador_id` | `coordinador` | esa sede |
   | figura como encargada en `profesor_sede` | `encargado` | esa sede |
   | coordina un área (`coordinador_area`) | `coordinador_area` | las sedes donde enseña |
   | tiene una beca activa | `becado` | global (marca, sin permisos) |

2. **Asignaciones explícitas** (Administración › Usuarios y permisos): un rol o un permiso
   suelto, con alcance y vigencia opcional (`desde` / `hasta`). Ej.: *Contador — Global*,
   *Responsable de inventario — Varela*, *permiso `gastos.view` — Quilmes*.

3. **Heredado**: `users.role = admin|direccion` o rol Spatie `admin`/`direccion` ⇒
   `administrador` global. La migración de plataforma los convierte en asignaciones de
   `superadministrador` (mismos privilegios que tenían: podían crear administradores).

### Alcance

| Alcance del permiso | Alcanza a |
|---------------------|-----------|
| global | toda la escuela |
| sede X | todo lo de la sede X (alumnos con sede principal o bloque en X, bloques, inventario, gastos, eventos de X) |
| bloque B | lo del bloque B (sus alumnos, su asistencia, sus eventos). Para *ver* eventos también alcanza a los de la sede del bloque y los generales |

- Crear o eliminar **sedes**, cerrar el mes, gestionar **usuarios** y gastos sin sede requieren alcance **global**.
- Un pago alcanza a los alumnos de su detalle: para **anularlo o editarlo** hay que tener alcance sobre **todos** ellos.
- `superadministrador` pasa todas las verificaciones. `administrador` tiene todo salvo
  `usuarios.assign_admin` (no puede crear ni quitar administradores).
- Siempre queda al menos un superadministrador (el sistema impide quitar el último).

### Dónde se verifica

| Capa | Qué hace |
|------|----------|
| `permiso:*` (middleware de ruta) | ¿tiene el permiso en algún ámbito? Si no: 403 |
| Policies (`app/Policies`) | ¿el registro concreto (este alumno, esta sede, este pago) cae en su alcance? Cambiar el ID en la URL no da acceso |
| Consultas de listado | `Alcance::aplicarAlumnos/aplicarBloques/aplicarPorSede/aplicarEventos` filtran lo que se muestra |
| `Gate::before` | `can('pagos.create')` responde con los permisos efectivos; superadmin pasa todo |

`User::isAdmin()`, `isProfesor()`, `acotaPorSede()`, etc. siguen existiendo por compatibilidad
con vistas antiguas, pero se calculan con este mismo motor.

### Contexto de trabajo ("Estoy trabajando como…")

Web (menú de usuario) y app (inicio / perfil). Solo cambia la **vista inicial**: nunca amplía
ni reduce permisos. API: header `X-Contexto: <rol>:<sede_id|global>`.

## Roles

| Rol | Alcances | Se asigna | Permisos |
|-----|----------|-----------|----------|
| **Superadministrador** (`superadministrador`) | global | asignación | todos |
| **Administrador** (`administrador`) | global, sede | asignación | todos excepto usuarios.assign_admin |
| **Coordinador** (`coordinador`) | sede, global | asignación | personas.view, personas.create, personas.update, alumnos.view, alumnos.create, alumnos.update, alumnos.delete, alumnos.export, profesores.view, sedes.view, sedes.manage, bloques.view, bloques.manage, bloques.delete, asistencias.view, asistencias.create, asistencias.update, asistencias.delete, seguimiento.view, seguimiento.create, comprobantes.view, comprobantes.create, eventos.view, eventos.create, eventos.update, eventos.delete, shows.view, shows.manage, calendario.view, villa_gesell.manage, partituras.view, reportes.view, inventario.view, becas.view |
| **Coordinador de área** (`coordinador_area`) | sede, global | asignación | personas.view, alumnos.view, alumnos.create, alumnos.update, alumnos.export, asistencias.view, asistencias.create, asistencias.update, asistencias.delete, seguimiento.view, seguimiento.create, calendario.view, eventos.view, partituras.view, bloques.view |
| **Profesor** (`profesor`) | bloque, sede | asignación | alumnos.view, bloques.view, asistencias.view, asistencias.create, asistencias.update, seguimiento.view, seguimiento.create, comprobantes.view, comprobantes.create, pagos.view, eventos.view, calendario.view, partituras.view |
| **Alumno** (`alumno`) | bloque | derivado | eventos.view, calendario.view, partituras.view |
| **Becado** (`becado`) | global | derivado | — |
| **Encargado** (`encargado`) | sede | asignación | sedes.view, bloques.view, eventos.view, calendario.view, inventario.view, inventario.create, inventario.update, compras.view, compras.create |
| **Responsable de sede** (`responsable_de_sede`) | sede | asignación | sedes.view, sedes.manage, bloques.view, alumnos.view, personas.view, eventos.view, eventos.create, eventos.update, calendario.view, inventario.view, inventario.create, inventario.update, gastos.view, gastos.create, compras.view, compras.create |
| **Responsable de inventario** (`responsable_de_inventario`) | sede, global | asignación | sedes.view, inventario.view, inventario.create, inventario.update, inventario.delete, compras.view, compras.create |
| **Administrativo** (`administrativo`) | global, sede | asignación | personas.view, personas.create, personas.update, alumnos.view, alumnos.create, alumnos.update, alumnos.export, profesores.view, sedes.view, bloques.view, cuotas.view, becas.view, pagos.view, pagos.create, comprobantes.view, comprobantes.create, comprobantes.approve, eventos.view, eventos.create, eventos.update, calendario.view, notificaciones.send |
| **Contador** (`contador`) | global, sede | asignación | cuotas.view, becas.view, pagos.view, comprobantes.view, facturacion.view, gastos.view, compras.view, reportes.view, sedes.view |
| **Tesorero** (`tesorero`) | global, sede | asignación | alumnos.view, personas.view, sedes.view, bloques.view, cuotas.view, cuotas.create, cuotas.update, cuotas.delete, becas.view, becas.manage, pagos.view, pagos.create, pagos.update, pagos.reverse, comprobantes.view, comprobantes.create, comprobantes.approve, facturacion.view, facturacion.manage, gastos.view, gastos.create, gastos.update, gastos.approve, compras.view, compras.approve, reportes.view, notificaciones.send |

## Permisos

| Módulo | Permisos |
|--------|----------|
| Personas | `personas.view` — Ver personas<br>`personas.create` — Crear personas<br>`personas.update` — Editar personas<br>`personas.delete` — Dar de baja personas<br>`personas.merge` — Fusionar personas duplicadas |
| Alumnos | `alumnos.view` — Ver alumnos<br>`alumnos.create` — Crear alumnos<br>`alumnos.update` — Editar alumnos<br>`alumnos.delete` — Eliminar alumnos<br>`alumnos.import` — Importar alumnos<br>`alumnos.export` — Exportar alumnos |
| Profesores | `profesores.view` — Ver profesores<br>`profesores.create` — Crear profesores<br>`profesores.update` — Editar profesores<br>`profesores.delete` — Eliminar profesores |
| Sedes | `sedes.view` — Ver sedes<br>`sedes.manage` — Crear y editar sedes<br>`sedes.delete` — Eliminar sedes |
| Bloques | `bloques.view` — Ver bloques<br>`bloques.manage` — Crear y editar bloques<br>`bloques.delete` — Eliminar bloques |
| Asistencias | `asistencias.view` — Ver asistencias<br>`asistencias.create` — Tomar asistencia<br>`asistencias.update` — Corregir asistencias<br>`asistencias.delete` — Eliminar asistencias |
| Seguimiento pedagógico | `seguimiento.view` — Ver cuaderno pedagógico<br>`seguimiento.create` — Escribir observaciones |
| Cuotas | `cuotas.view` — Ver cuotas<br>`cuotas.create` — Crear cuotas<br>`cuotas.update` — Editar cuotas<br>`cuotas.delete` — Eliminar cuotas |
| Becas | `becas.view` — Ver becas<br>`becas.manage` — Otorgar y editar becas |
| Pagos | `pagos.view` — Ver pagos<br>`pagos.create` — Registrar pagos<br>`pagos.update` — Editar pagos<br>`pagos.reverse` — Anular pagos<br>`comprobantes.view` — Ver comprobantes enviados por alumnos<br>`comprobantes.create` — Cargar comprobantes de alumnos<br>`comprobantes.approve` — Aprobar comprobantes (genera pago) |
| Facturación | `facturacion.view` — Ver facturación<br>`facturacion.manage` — Cargar facturación mensual |
| Gastos | `gastos.view` — Ver gastos<br>`gastos.create` — Registrar gastos<br>`gastos.update` — Editar gastos<br>`gastos.delete` — Eliminar gastos<br>`gastos.approve` — Aprobar gastos |
| Inventario | `inventario.view` — Ver inventario<br>`inventario.create` — Cargar ítems<br>`inventario.update` — Editar ítems y registrar movimientos<br>`inventario.delete` — Eliminar ítems |
| Compras | `compras.view` — Ver plan y órdenes de compra<br>`compras.create` — Crear órdenes de compra<br>`compras.approve` — Aprobar órdenes de compra |
| Eventos | `eventos.view` — Ver eventos<br>`eventos.create` — Crear eventos<br>`eventos.update` — Editar eventos<br>`eventos.delete` — Eliminar eventos<br>`shows.view` — Ver shows<br>`shows.manage` — Gestionar shows<br>`calendario.view` — Ver calendario<br>`villa_gesell.manage` — Gestionar gira Villa Gesell |
| Programa y partituras | `partituras.view` — Ver partituras<br>`partituras.admin` — Administrar programa y partituras<br>`biblioteca.admin` — Moderar biblioteca<br>`disenos.manage` — Usar el módulo Diseño |
| Reportes | `reportes.view` — Ver reportes<br>`auditoria.view` — Ver auditoría |
| Notificaciones | `notificaciones.send` — Enviar recordatorios y avisos |
| Usuarios | `usuarios.view` — Ver usuarios<br>`usuarios.create` — Crear usuarios<br>`usuarios.update` — Editar, activar y resetear usuarios<br>`usuarios.permissions` — Asignar roles y permisos<br>`usuarios.assign_admin` — Asignar roles de administración |

## Ejemplo: "un profesor también es contador"

María Gómez: alumna en Banfield (bloque 3), profesora en Palomar (bloque 5), coordinadora en
Quilmes, encargada de inventario en Varela y contadora global. **Una persona, una cuenta**:

| Rol | Dónde | Origen |
|-----|-------|--------|
| Alumno | Banfield 3 | inscripción |
| Profesor | Palomar 5 | ficha docente |
| Coordinador | Quilmes | ficha docente (sede) |
| Encargado | Varela | asignación |
| Contador | Global | asignación |

Resultado (test `AutorizacionContextualTest::test_caso_combinado_una_persona_cinco_funciones`):
toma asistencia en Palomar 5 pero no en Varela; edita la sede Quilmes pero no Palomar; no ve
a sus compañeros de Banfield (sí su propia ficha); gestiona el inventario de Varela y no el
de Banfield; consulta pagos, cuotas, gastos y reportes de toda la escuela pero no registra
pagos ni administra usuarios.

## Agregar un rol o permiso

1. Editar `config/permisos.php` (grupo del permiso, roles que lo incluyen, módulo de menú si corresponde).
2. Proteger la ruta con `permiso:...` y, si el recurso tiene sede/bloque, verificar el alcance en su Policy.
3. `php artisan chilinga:permisos:sync` (también corre en cada deploy desde `start.sh`).
4. Test en `tests/Feature/AutorizacionContextualTest.php`.
