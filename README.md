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

## 📦 CRUDs disponibles (carga de datos)

El sistema incluye todos los CRUD necesarios para la carga y gestión de datos:

| Módulo | Descripción | Rutas |
|--------|-------------|--------|
| **Alumnos** | Alta, edición, baja y listado con filtros; vista detalle | `/alumnos` |
| **Profesores** | CRUD completo (nombre, teléfono, correo, activo) | `/profesores` |
| **Sedes** | CRUD completo (nombre, dirección, tipo propiedad, alquiler) | `/sedes` |
| **Bloques** | CRUD por año, profesor, sede, horarios | `/bloques` |
| **Eventos** | CRUD de eventos (shows, talleres, muestras, etc.) | `/eventos` |
| **Shows** | Próximos shows (bloques o convocatoria abierta) | `/shows` |
| **Inventarios** | **Tambores e instrumentos**, herramientas, accesorios, repuestos, parches, telas, masas por sede; atributos (marca, medida, propietario escuela/alumno, estado) | `/inventarios` |
| **Plan de compras** | Consulta de sugerencias por sede (no CRUD) | `/plan-compras` |
| **Órdenes de compra** | CRUD de órdenes e ítems | `/ordenes-compra` |
| **Cuotas** | CRUD de cuotas | `/cuotas` |
| **Pagos** | Alta y listado con trazabilidad y PDF | `/pagos` |
| **Facturación mensual** | Alta y edición por mes | `/facturacion-mensual` |
| **Gastos** | CRUD (sueldos, alquiler, servicios, reparaciones) | `/gastos` |
| **Asistencias** | CRUD de asistencias por bloque | `/asistencias` |

**Tambores:** no hay un CRUD aparte llamado "Tambores". Los instrumentos (repiques, surdos, timbales, etc.) se cargan en **Inventarios** con tipo *Instrumentos*, por sede, con características (marca, diámetro, torres, propietario escuela/alumno, estado, origen).

## 📋 Requisitos

- PHP 8.1 o superior
- Composer
- MySQL 5.7 o superior
- Node.js y NPM

## 🚀 Ejecución rápida

Para tener **todo listo para ejecutar** (local, Docker o Railway), seguí la guía **[EJECUCION.md](EJECUCION.md)**. Incluye variables obligatorias, primer arranque, Docker, Railway y checklist de producción.

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

## 📥 Importar datos desde Excel (Chilinga 2025)

Para cargar muchos datos de prueba desde el Excel **"Chilinga 2025 _ 30 años (Respuestas).xlsx"** (hojas Formulario, Cuotas, RemerasBloque, Hoja 4):

```bash
# Ver qué se importaría sin tocar la base (dry-run)
php artisan chilinga:import-excel ruta/al/archivo.xlsx --dry-run

# Importar (crea/actualiza sedes, profesor, bloque, alumnos, cuotas)
php artisan chilinga:import-excel ruta/al/archivo.xlsx

# Vaciar sedes, bloques, profesores, alumnos, cuotas y volver a importar
php artisan chilinga:import-excel ruta/al/archivo.xlsx --fresh
```

Se importan: **Sedes** (desde columna Sede del Formulario), **un profesor** (desde RemerasBloque), **un bloque** "Trinchera Sur", **alumnos** (Nombre, DNI, Fecha nac., teléfono, tambor, sede) y **cuotas** por mes (Marzo–diciembre 2025) desde la hoja Cuotas.

## 👤 Usuarios por Defecto

**Administrador:**
- Usuario: `admin`
- Contraseña: `admin123`

**Profesor:**
- Usuario: `profesor`
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
