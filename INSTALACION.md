# Guía de Instalación - Sistema La Chilinga

## Pasos de Instalación

### 1. Configurar Base de Datos

Crear una base de datos MySQL:
```sql
CREATE DATABASE chilinga_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configurar .env

Editar el archivo `.env` con tus credenciales:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chilinga_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 3. Ejecutar Migraciones

```bash
php artisan migrate
```

### 4. Ejecutar Seeders

```bash
php artisan db:seed
```

Esto creará:
- Las 6 sedes (Palomar, Saavedra, Varela, Quilmes, Banfield, Tacheles)
- Usuario admin: `admin@chilinga.com` / `admin123`
- Usuario profesor: `profesor@chilinga.com` / `profesor123`
- Roles de permisos

### 5. Compilar Assets

```bash
npm run build
```

O para desarrollo:
```bash
npm run dev
```

### 6. Iniciar Servidor

```bash
php artisan serve
```

Acceder a: `http://localhost:8000`

## Estructura de Permisos

### Admin
- Acceso completo a todos los módulos
- CRUD de alumnos, profesores, bloques, sedes, eventos
- Ver métricas y dashboard completo
- Exportar datos

### Profesor
- Ver sus bloques asignados
- Ver alumnos de sus bloques
- Ver calendario de sus talleres
- Marcar asistencia
- Ver historial de eventos

## Notas Importantes

- Las contraseñas por defecto deben cambiarse en producción
- El sistema usa sesiones para autenticación
- Los roles se gestionan con Spatie Permission
- Las exportaciones Excel usan Maatwebsite/Excel

