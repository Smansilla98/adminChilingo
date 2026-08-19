<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('observaciones_pedagogicas')) {
            return;
        }
        Schema::table('observaciones_pedagogicas', function (Blueprint $table) {
            if (! Schema::hasColumn('observaciones_pedagogicas', 'eje')) {
                $table->string('eje', 32)->nullable();
            }
            if (! Schema::hasColumn('observaciones_pedagogicas', 'proximo_paso')) {
                $table->string('proximo_paso', 400)->nullable();
            }
            if (! Schema::hasColumn('observaciones_pedagogicas', 'visible_alumno')) {
                $table->boolean('visible_alumno')->default(false);
            }
        });

        if (Schema::hasTable('asistencias') && ! $this->indexExists('asistencias', 'asistencias_bloque_fecha_idx')) {
            Schema::table('asistencias', function (Blueprint $table) {
                $table->index(['bloque_id', 'fecha'], 'asistencias_bloque_fecha_idx');
            });
        }
        if (Schema::hasTable('comprobantes_cuota_alumnos') && ! $this->indexExists('comprobantes_cuota_alumnos', 'comprobantes_estado_idx')) {
            Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
                $table->index('estado', 'comprobantes_estado_idx');
            });
        }
        if (Schema::hasTable('alumnos') && ! $this->indexExists('alumnos', 'alumnos_activo_sede_idx')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->index(['activo', 'sede_id'], 'alumnos_activo_sede_idx');
            });
        }
    }

    public function down(): void
    {
        //
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            $sm = Schema::getConnection()->getSchemaBuilder();
            if (method_exists($sm, 'hasIndex')) {
                return $sm->hasIndex($table, $name);
            }
        } catch (\Throwable) {
        }

        return false;
    }
};
