<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permitir más tipos de evento: aniversario, fiesta, rifa, etc.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE eventos MODIFY tipo_evento VARCHAR(50) NOT NULL DEFAULT 'taller'");
        }
        // SQLite y otros: la columna puede seguir siendo string; no se modifica
    }

    public function down(): void
    {
        // Revertir a enum no es trivial; se deja como string
    }
};
