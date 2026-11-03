<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Segunda tanda de la segunda barrera: el historial (asistencias, comprobantes,
     * becas, observaciones, Villa Gesell) y lo que cuelga de una sede ya no se borra
     * en cascada. La aplicación lo verifica antes (EliminacionSegura) y ofrece
     * desactivar; las tablas puente (alumno_bloque, bloque_profesor, horarios,
     * show_bloque, asignaciones) siguen en cascada porque no son historial.
     *
     * Solo MySQL, igual que 2026_10_27_000002.
     */
    private const CLAVES = [
        // tabla => [columna => [tabla referida, regla nueva, regla anterior]]
        'bloques' => ['sede_id' => ['sedes', 'RESTRICT', 'CASCADE']],
        'asistencias' => [
            'alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE'],
            'bloque_id' => ['bloques', 'RESTRICT', 'CASCADE'],
        ],
        'comprobantes_cuota_alumnos' => ['alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE']],
        'comprobante_cuota_alumno_items' => [
            'cuota_id' => ['cuotas', 'RESTRICT', 'CASCADE'],
            'bloque_id' => ['bloques', 'RESTRICT', 'CASCADE'],
        ],
        'becas' => ['alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE']],
        'observaciones_pedagogicas' => ['alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE']],
        'villa_gesell_inscriptos' => ['alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE']],
        'inventario_items' => ['sede_id' => ['sedes', 'RESTRICT', 'CASCADE']],
        'facturacion_mensual' => ['sede_id' => ['sedes', 'RESTRICT', 'CASCADE']],
        'ordenes_compra' => ['sede_id' => ['sedes', 'RESTRICT', 'CASCADE']],
    ];

    public function up(): void
    {
        $this->aplicar(1);
    }

    public function down(): void
    {
        $this->aplicar(2);
    }

    private function aplicar(int $regla): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::CLAVES as $tabla => $columnas) {
            $quitar = [];
            $agregar = [];
            foreach ($columnas as $columna => $def) {
                $actual = DB::selectOne(
                    'SELECT k.CONSTRAINT_NAME AS nombre, r.DELETE_RULE AS regla
                     FROM information_schema.KEY_COLUMN_USAGE k
                     JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                       ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                     WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME = ?',
                    [$tabla, $columna, $def[0]],
                );
                if ($actual === null || strtoupper((string) $actual->regla) === $def[$regla]) {
                    continue; // no existe o ya tiene la regla buscada
                }
                // Volver a crear la clave valida todas las filas: con huérfanos fallaría después de
                // haberla borrado y la tabla quedaría sin clave. En ese caso se deja como está.
                $huerfanos = DB::table($tabla)->whereNotNull($columna)
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($def[0])->whereColumn("{$def[0]}.id", "{$tabla}.{$columna}"))
                    ->count();
                if ($huerfanos > 0) {
                    Log::warning("FK {$tabla}.{$columna}: {$huerfanos} filas sin {$def[0]}; queda con ON DELETE ".strtoupper((string) $actual->regla).'.');

                    continue;
                }
                $nombre = str_replace('`', '', (string) $actual->nombre);
                $quitar[] = "DROP FOREIGN KEY `{$nombre}`";
                $agregar[] = "ADD CONSTRAINT `{$nombre}` FOREIGN KEY (`{$columna}`) REFERENCES `{$def[0]}` (`id`) ON DELETE {$def[$regla]}";
            }
            if ($quitar !== []) {
                DB::statement("ALTER TABLE `{$tabla}` ".implode(', ', $quitar));
                DB::statement("ALTER TABLE `{$tabla}` ".implode(', ', $agregar));
            }
        }
    }
};
