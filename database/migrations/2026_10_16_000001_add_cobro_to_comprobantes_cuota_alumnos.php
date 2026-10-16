<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago de cuotas iniciado desde el sistema: el comprobante nace como "esperando pago"
 * con un código de referencia, y se completa con la transferencia o con Mercado Pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            return;
        }
        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobantes_cuota_alumnos', 'origen')) {
                $table->string('origen', 20)->default('comprobante');
            }
            if (! Schema::hasColumn('comprobantes_cuota_alumnos', 'referencia')) {
                $table->string('referencia', 16)->nullable()->unique();
            }
            if (! Schema::hasColumn('comprobantes_cuota_alumnos', 'mp_preference_id')) {
                $table->string('mp_preference_id', 100)->nullable();
            }
            if (! Schema::hasColumn('comprobantes_cuota_alumnos', 'mp_payment_id')) {
                $table->string('mp_payment_id', 40)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            return;
        }
        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            foreach (['mp_payment_id', 'mp_preference_id', 'referencia', 'origen'] as $col) {
                if (Schema::hasColumn('comprobantes_cuota_alumnos', $col)) {
                    if ($col === 'referencia') {
                        $table->dropUnique(['referencia']);
                    }
                    if ($col === 'mp_payment_id') {
                        $table->dropIndex(['mp_payment_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
