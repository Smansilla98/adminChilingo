<?php

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Personas\BackfillPersonas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Migración de datos (idempotente, no destructiva):
 *  - vuelca el catálogo de permisos/roles (config/permisos.php) a las tablas de Spatie;
 *  - crea una Persona por cada perfil existente y vincula alumnos, profesores y usuarios;
 *  - convierte dirección heredada (admin/direccion) en asignación "administrador" global.
 *
 * Se puede re-ejecutar a mano con `php artisan chilinga:personas:backfill`.
 */
return new class extends Migration
{
    public function up(): void
    {
        CatalogoPermisos::sincronizar();
        $stats = app(BackfillPersonas::class)->ejecutar();
        Log::info('Backfill de personas', $stats);
    }

    public function down(): void
    {
        // Sin reversión: los vínculos persona_id se descartan al revertir la migración que crea las columnas.
    }
};
