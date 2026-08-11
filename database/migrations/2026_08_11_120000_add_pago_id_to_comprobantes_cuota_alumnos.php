<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            return;
        }

        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobantes_cuota_alumnos', 'pago_id')) {
                if (Schema::hasTable('pagos')) {
                    $table->foreignId('pago_id')
                        ->nullable()
                        ->after('estado')
                        ->constrained('pagos')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('pago_id')->nullable()->after('estado');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')
            || ! Schema::hasColumn('comprobantes_cuota_alumnos', 'pago_id')) {
            return;
        }

        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            try {
                $table->dropForeign(['pago_id']);
            } catch (\Throwable $e) {
            }
            $table->dropColumn('pago_id');
        });
    }
};
