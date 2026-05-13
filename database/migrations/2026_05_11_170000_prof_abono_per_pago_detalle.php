<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abono al profesor por cada alumno/cuota (base × %), y traslado desde pagos si existía.
     */
    public function up(): void
    {
        if (Schema::hasTable('pago_detalles')) {
            Schema::table('pago_detalles', function (Blueprint $table) {
                if (! Schema::hasColumn('pago_detalles', 'abono_profesor')) {
                    $table->decimal('abono_profesor', 12, 2)->nullable()->after('monto');
                }
                if (! Schema::hasColumn('pago_detalles', 'abono_base')) {
                    $table->decimal('abono_base', 12, 2)->nullable()->after('abono_profesor');
                }
                if (! Schema::hasColumn('pago_detalles', 'abono_porcentaje')) {
                    $table->decimal('abono_porcentaje', 5, 2)->nullable()->after('abono_base');
                }
                if (! Schema::hasColumn('pago_detalles', 'abono_nota')) {
                    $table->string('abono_nota', 500)->nullable()->after('abono_porcentaje');
                }
            });
        }

        if (Schema::hasTable('pagos') && Schema::hasColumn('pagos', 'abono_profesor')) {
            $pagos = DB::table('pagos')->whereNotNull('abono_profesor')->get(['id', 'abono_profesor', 'abono_profesor_nota']);
            foreach ($pagos as $pago) {
                $detIds = DB::table('pago_detalles')->where('pago_id', $pago->id)->pluck('id');
                $n = $detIds->count();
                if ($n === 0) {
                    continue;
                }
                $porDetalle = round((float) $pago->abono_profesor / $n, 2);
                $delta = (float) $pago->abono_profesor - ($porDetalle * $n);
                foreach ($detIds as $i => $did) {
                    $monto = $porDetalle + ($i === 0 ? $delta : 0);
                    DB::table('pago_detalles')->where('id', $did)->update([
                        'abono_profesor' => $monto,
                        'abono_base' => null,
                        'abono_porcentaje' => null,
                        'abono_nota' => $pago->abono_profesor_nota,
                    ]);
                }
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
    }

    public function down(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (! Schema::hasColumn('pagos', 'abono_profesor')) {
                    $table->decimal('abono_profesor', 12, 2)->nullable()->after('monto_total');
                }
                if (! Schema::hasColumn('pagos', 'abono_profesor_nota')) {
                    $table->string('abono_profesor_nota', 500)->nullable()->after('abono_profesor');
                }
            });
        }

        if (Schema::hasTable('pago_detalles')) {
            Schema::table('pago_detalles', function (Blueprint $table) {
                foreach (['abono_nota', 'abono_porcentaje', 'abono_base', 'abono_profesor'] as $col) {
                    if (Schema::hasColumn('pago_detalles', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
