<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por sede: cuánto de la cuota de referencia no entra en la base del docente y % del docente sobre la base.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sedes')) {
            return;
        }
        Schema::table('sedes', function (Blueprint $table) {
            if (! Schema::hasColumn('sedes', 'liquidacion_retencion_escuela')) {
                $table->decimal('liquidacion_retencion_escuela', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('sedes', 'liquidacion_porc_docente')) {
                $table->decimal('liquidacion_porc_docente', 5, 2)->default(40);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sedes')) {
            return;
        }
        Schema::table('sedes', function (Blueprint $table) {
            if (Schema::hasColumn('sedes', 'liquidacion_porc_docente')) {
                $table->dropColumn('liquidacion_porc_docente');
            }
            if (Schema::hasColumn('sedes', 'liquidacion_retencion_escuela')) {
                $table->dropColumn('liquidacion_retencion_escuela');
            }
        });
    }
};
