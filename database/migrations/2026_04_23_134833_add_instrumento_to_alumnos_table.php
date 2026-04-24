<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('alumnos')) {
            return;
        }

        // 1) Agregar procedencia si no existe
        if (!Schema::hasColumn('alumnos', 'tambor_procedencia')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->string('tambor_procedencia')->nullable()->after('instrumento_secundario');
            });
        }

        // 2) Copiar datos del campo viejo tipo_tambor (Sede/Propio) hacia tambor_procedencia
        if (Schema::hasColumn('alumnos', 'tipo_tambor')) {
            DB::table('alumnos')->update([
                'tambor_procedencia' => DB::raw('tipo_tambor'),
            ]);
        }

        // 3) Reemplazar tipo_tambor: de enum procedencia → string nullable tipo instrumento
        // (evitamos depender de doctrine/dbal: drop + recreate)
        if (Schema::hasColumn('alumnos', 'tipo_tambor')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->dropColumn('tipo_tambor');
            });
        }

        Schema::table('alumnos', function (Blueprint $table) {
            if (!Schema::hasColumn('alumnos', 'tipo_tambor')) {
                $table->string('tipo_tambor')->nullable()->after('tambor_procedencia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('alumnos')) {
            return;
        }

        // Revertir a situación anterior: tipo_tambor como procedencia (enum) y sin tambor_procedencia
        if (Schema::hasColumn('alumnos', 'tipo_tambor')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->dropColumn('tipo_tambor');
            });
        }

        Schema::table('alumnos', function (Blueprint $table) {
            if (!Schema::hasColumn('alumnos', 'tipo_tambor')) {
                $table->enum('tipo_tambor', ['Sede', 'Propio'])->default('Sede')->after('instrumento_secundario');
            }
        });

        if (Schema::hasColumn('alumnos', 'tambor_procedencia')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->dropColumn('tambor_procedencia');
            });
        }
    }
};
