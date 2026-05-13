<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abono al profesor del bloque (monto manual y nota de referencia hasta reglas automáticas).
     */
    public function up(): void
    {
        if (! Schema::hasTable('pagos')) {
            return;
        }
        Schema::table('pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('pagos', 'abono_profesor')) {
                $table->decimal('abono_profesor', 12, 2)->nullable()->after('monto_total');
            }
            if (! Schema::hasColumn('pagos', 'abono_profesor_nota')) {
                $table->string('abono_profesor_nota', 500)->nullable()->after('abono_profesor');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pagos')) {
            return;
        }
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'abono_profesor_nota')) {
                $table->dropColumn('abono_profesor_nota');
            }
            if (Schema::hasColumn('pagos', 'abono_profesor')) {
                $table->dropColumn('abono_profesor');
            }
        });
    }
};
