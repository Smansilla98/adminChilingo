# Recomendaciones — Sistema de Gestión La Chilinga

Resumen de lo que se agregó a partir del **programa oficial** de la escuela y qué más conviene tener en cuenta para que el sistema sea completamente funcional.

---

## Lo que se implementó con el programa

1. **Programa de ritmos (toques por año)**  
   - Tabla `programa_ritmos`: año (1–6), orden, nombre, autor, opcional, notas.  
   - Seeder `ProgramaRitmosSeeder` con todos los toques del texto del programa (1° a 6° año).  
   - Menú **Programa** → vista de solo lectura con el programa oficial por año.  
   - Comando: `php artisan db:seed --class=ProgramaRitmosSeeder` (o `php artisan db:seed` si corre el `DatabaseSeeder`).

2. **Sedes oficiales**  
   - Seeder `SedesSeeder` con las 6 sedes del programa: Palomar, Saavedra, Varela, Quilmes, Banfield, Tacheles (con direcciones).  
   - Al correr `db:seed` no se duplican sedes si ya existen por nombre.

3. **Tipos de evento alineados al programa**  
   - En **Eventos** se pueden elegir: **Muestra alumnos**, **Caminata 1er** (caminata de 1° año), **Show beneficio**, **Villa gesell**, además de show, taller, muestra, gira, aniversario, fiesta, rifa, otro.

---

## Lo que ya cubre el sistema

- **Bloques por año**: el modelo Bloque ya tiene `año` (1, 2, 3…). Se puede filtrar/reportar por año del bloque.  
- **Sin evaluación de rendimiento**: el programa aclara que a fin de año no se evalúa rendimiento; el sistema no tiene notas ni calificaciones, solo asistencias y participación.  
- **Inventario de tambores**: ~30 tambores por bloque, ítems en reparación; ya cubierto por Inventarios (estado, tipo, sede).  
- **Shows y eventos**: calendario, shows con bloques o convocatoria abierta, eventos por tipo (incluidas muestras, caminata, shows a beneficio, Villa Gesell).  
- **Cuotas, pagos, facturación, gastos, reportes**: todo lo administrativo y financiero ya está implementado.

---

## Recomendaciones para dejarlo completamente funcional

### 1. **Datos iniciales**

- Ejecutar migraciones y seeders en cada entorno (local, Railway):  
  `php artisan migrate --force` y `php artisan db:seed` (o los seeders que correspondan).  
- Revisar que las 6 sedes queden cargadas (SedesSeeder).  
- Si usás usuarios de ejemplo: `php artisan db:seed --class=UsersSeeder` (admin / profesor).

### 2. **Vincular profesor ↔ usuario**

- Para que el dashboard del profesor funcione, cada **Profesor** debe tener `user_id` apuntando al **User** que inicia sesión.  
- En **Profesores** → editar → asignar “Usuario” (si existe el campo) o asegurarse de que la relación User ↔ Profesor esté bien usada en el código.

### 3. **Comprobantes PDF en producción**

- En Railway el disco es efímero: los PDF de pagos en `storage/app/public` se pierden al redeploy.  
- Opciones: usar un volumen persistente (si Railway lo ofrece) o almacenamiento externo (S3, etc.) y configurar `config/filesystems.php` + `FILESYSTEM_DISK` en producción.

### 4. **Reportes por período**

- Hoy los reportes pueden ser “todo el tiempo”.  
- Agregar filtros por **año** y **mes** (o rango de fechas) en la vista de Reportes para cortes mensuales o anuales.

### 5. **Página “La escuela” o texto del programa**

- Opcional: una página estática (o con contenido desde BD) con el texto completo del programa (comienzos, fundamentos, objetivos por año, instrumentos, talleres, Villa Gesell, etc.) para consulta interna o para familias.  
- Podría ser una ruta `/la-escuela` o `/programa-completo` con el texto que compartiste.

### 6. **Recordatorios o notificaciones (opcional)**

- Recordatorios de pagos de cuotas, avisos de próximos eventos o muestras.  
- Implementar con colas (queue), mails o notificaciones en la app según el alcance que quieras.

**WhatsApp (bot / mensajes automáticos):**  
Sí se puede usar WhatsApp para estos avisos. Opciones prácticas:

- **WhatsApp Business API (Meta)** o **Twilio para WhatsApp**: envío de mensajes desde la app (plantillas aprobadas por Meta para mensajes iniciados por el negocio). Laravel puede tener un comando programado (cron) que cada día consulte cuotas por vencer o próximos eventos y envíe mensajes vía API.
- **Servicios intermedios** (Wati, 360dialog, MessageBird, etc.): facilitan el uso de la API de WhatsApp y a veces incluyen panel y flujos tipo “bot”.
- **Flujo sugerido**: comando `php artisan chilinga:recordatorios` que (1) busque alumnos con cuota impaga o evento en los próximos X días, (2) envíe un mensaje por WhatsApp usando la API (o el servicio elegido). El “bot” es en realidad la app enviando mensajes en forma automática según reglas (fechas, cuotas, eventos).

Importante: usar solo APIs oficiales o servicios que las usen; soluciones no oficiales (ej. whatsapp-web.js) violan los términos de WhatsApp y pueden resultar en bloqueos.

### 7. **Backup de base de datos**

- Definir backups periódicos de MySQL (Railway u otro proveedor).  
- Documentar en DEPLOY.md cómo restaurar si hace falta.

### 8. **Permisos por rol**

- Ya tienes roles (admin, profesor, etc.) y middleware.  
- Revisar que cada ruta sensible (reportes, gastos, cuotas, usuarios) esté protegida según el rol y que los profesores solo vean lo que les corresponde.

---

## Checklist rápido de puesta en marcha

- [ ] Migraciones ejecutadas.  
- [ ] Seeders ejecutados (Sedes, Users, ProgramaRitmos).  
- [ ] Sedes creadas/actualizadas con las 6 del programa.  
- [ ] Profesores vinculados a usuarios donde aplique.  
- [ ] Variables de entorno de producción (APP_DEBUG=false, APP_URL, DB_*, SESSION_SECURE_COOKIE).  
- [ ] `storage:link` ejecutado; si hay producción con PDF, definir almacenamiento persistente o S3.  
- [ ] Revisar permisos y que los reportes/calendario/programa se vean según el rol.

Con el **Programa** cargado, las **sedes** y los **tipos de evento** alineados al texto del programa, el sistema queda alineado al funcionamiento real de La Chilinga y listo para uso diario; el resto son mejoras opcionales o de producción (backups, reportes por período, página “La escuela”, notificaciones).
