# Sistema de Gestión Administrativa - La Chilinga

Sistema de gestión administrativa para la escuela de percusión La Chilinga, fundada por Dani Buira.

## 🚀 Características

- **Autenticación con roles**: Admin y Profesor
- **Gestión completa de alumnos**: CRUD con validaciones, exportación a Excel
- **Gestión de profesores**: CRUD completo
- **Gestión de bloques**: Por año (1° a 6°), con asignación de profesores
- **Gestión de sedes**: 6 sedes (Palomar, Saavedra, Varela, Quilmes, Banfield, Tacheles)
- **Gestión de eventos**: Shows, talleres, muestras, giras
- **Calendario interactivo**: FullCalendar con filtros por sede y profesor
- **Sistema de asistencias**: Registro de asistencia por bloque
- **Dashboard con métricas**: Gráficos con Chart.js
- **Exportación a Excel**: Para alumnos

## 📋 Requisitos

- PHP 8.1 o superior
- Composer
- MySQL 5.7 o superior
- Node.js y NPM

## 🔧 Instalación

1. Clonar o navegar al proyecto:
```bash
cd chilinga-admin
```

2. Instalar dependencias:
```bash
composer install
npm install
```

3. Configurar el archivo `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configurar la base de datos en `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chilinga_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

5. Ejecutar migraciones y seeders:
```bash
php artisan migrate
php artisan db:seed
```

6. Compilar assets:
```bash
npm run build
```

7. Iniciar el servidor:
```bash
php artisan serve
```

## 👤 Usuarios por Defecto

**Administrador:**
- Email: `admin@chilinga.com`
- Contraseña: `admin123`

**Profesor:**
- Email: `profesor@chilinga.com`
- Contraseña: `profesor123`

## 📁 Estructura del Proyecto

```
chilinga-admin/
├── app/
│   ├── Exports/          # Exportaciones Excel
│   ├── Http/
│   │   ├── Controllers/  # Controladores
│   │   └── Middleware/   # Middleware de roles
│   └── Models/          # Modelos Eloquent
├── database/
│   ├── migrations/      # Migraciones
│   └── seeders/         # Seeders
├── resources/
│   └── views/           # Vistas Blade
└── routes/
    └── web.php         # Rutas web
```

## 🎯 Módulos Principales

### 1. Autenticación y Roles
- Login seguro con sesiones
- Roles: Admin y Profesor
- Middleware de control de acceso

### 2. Dashboard
- Total de alumnos activos
- Alumnos por sede (gráfico)
- Alumnos por año (1° a 6°)
- Cantidad de bloques activos
- Próximos eventos
- % alumnos con tambor propio vs sede

### 3. Gestión de Alumnos
- CRUD completo
- Validación DNI único
- Cálculo automático de edad
- Filtros por sede y año
- Exportación a Excel

### 4. Gestión de Bloques
- Por año (1° a 6°)
- Asignación de profesor
- Control de cupos
- Lista de alumnos

### 5. Calendario
- Vista mensual, semanal y diaria
- Filtros por sede y profesor
- Creación de eventos
- Integración con FullCalendar

### 6. Asistencias
- Registro por bloque
- Fecha específica
- Historial de asistencias

## 🔐 Seguridad

- Protección CSRF
- Passwords con bcrypt
- Validaciones backend
- Prepared statements
- Control de acceso por rol

## 🎨 Diseño

- Bootstrap 5
- Colores relacionados a percusión/cultura popular
- UI clara y simple
- Responsive
- Navegación lateral

## 📊 Base de Datos

### Tablas principales:
- `users` - Usuarios del sistema
- `sedes` - Sedes de la escuela
- `profesores` - Profesores
- `bloques` - Bloques por año
- `alumnos` - Alumnos
- `eventos` - Eventos, shows, talleres
- `asistencias` - Registro de asistencias

## 🚀 Futuras Mejoras

- Sistema de pagos
- Cuotas mensuales
- Integración MercadoPago
- App móvil
- Notificaciones por WhatsApp
- Firma digital de inscripción

## 📝 Licencia

Este proyecto es privado para La Chilinga.

## 👨‍💻 Desarrollo

Desarrollado con Laravel 12, PHP 8+, MySQL, Bootstrap 5 y Chart.js.
