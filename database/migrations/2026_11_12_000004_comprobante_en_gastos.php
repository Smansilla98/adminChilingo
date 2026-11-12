<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comprobante adjunto (PDF o imagen) a un gasto: factura o ticket del proveedor. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gastos') && ! Schema::hasColumn('gastos', 'comprobante_path')) {
            Schema::table('gastos', function (Blueprint $table) {
                $table->string('comprobante_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gastos') && Schema::hasColumn('gastos', 'comprobante_path')) {
            Schema::table('gastos', function (Blueprint $table) {
                $table->dropColumn('comprobante_path');
            });
        }
    }
};
