<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago "extra": el alumno paga un bloque y un mes aunque la cuota todavía no esté
 * emitida. La línea guarda año y mes, y la cuota se engancha al aprobar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_cuota_alumno_items')) {
            return;
        }
        Schema::table('comprobante_cuota_alumno_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cuota_id')->nullable()->change();
            if (! Schema::hasColumn('comprobante_cuota_alumno_items', 'anio')) {
                $table->unsignedSmallInteger('anio')->nullable();
            }
            if (! Schema::hasColumn('comprobante_cuota_alumno_items', 'mes')) {
                $table->unsignedTinyInteger('mes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobante_cuota_alumno_items')) {
            return;
        }
        Schema::table('comprobante_cuota_alumno_items', function (Blueprint $table) {
            foreach (['anio', 'mes'] as $col) {
                if (Schema::hasColumn('comprobante_cuota_alumno_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
