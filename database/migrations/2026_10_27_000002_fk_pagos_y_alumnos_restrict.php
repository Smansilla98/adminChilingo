<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Borrar una sede, un alumno o una cuota ya no arrastra en la base sus pagos
     * (ni, en el caso de la sede, a sus alumnos): la base lo rechaza. La aplicación
     * ya lo impedía (EliminacionSegura); esto es la segunda barrera.
     *
     * Solo MySQL: en SQLite (tests) cambiar una FK implica recrear la tabla y la
     * protección de la aplicación ya está cubierta por tests. Dos ALTER por tabla
     * (MySQL no deja reusar el nombre de la clave en la misma sentencia).
     */
    private const CLAVES = [
        // tabla => [columna => [tabla referida, regla nueva, regla anterior]]
        'alumnos' => ['sede_id' => ['sedes', 'RESTRICT', 'CASCADE']],
        'pago_detalles' => [
            'alumno_id' => ['alumnos', 'RESTRICT', 'CASCADE'],
            'cuota_id' => ['cuotas', 'RESTRICT', 'CASCADE'],
        ],
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
