<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return;
        }

        Schema::table('biblioteca_items', function (Blueprint $table) {
            if (! Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
                if (Schema::hasTable('programa_ritmos')) {
                    $table->foreignId('programa_ritmo_id')
                        ->nullable()
                        ->after('ip')
                        ->constrained('programa_ritmos')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('programa_ritmo_id')->nullable()->after('ip');
                }
            }

            if (! Schema::hasColumn('biblioteca_items', 'instrumento')) {
                $after = Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')
                    ? 'programa_ritmo_id'
                    : 'ip';
                $table->string('instrumento', 40)->nullable()->after($after);
            }
        });

        // Índice compuesto (si falla por duplicado, no bloquea)
        try {
            Schema::table('biblioteca_items', function (Blueprint $table) {
                $table->index(['programa_ritmo_id', 'instrumento'], 'biblioteca_items_toque_instr_idx');
            });
        } catch (\Throwable $e) {
            // índice ya existe u otra condición
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return;
        }

        Schema::table('biblioteca_items', function (Blueprint $table) {
            try {
                $table->dropIndex('biblioteca_items_toque_instr_idx');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
                try {
                    $table->dropForeign(['programa_ritmo_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('programa_ritmo_id');
            }

            if (Schema::hasColumn('biblioteca_items', 'instrumento')) {
                $table->dropColumn('instrumento');
            }
        });
    }
};
