<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_items')) {
            \Illuminate\Support\Facades\DB::table('inventario_items')->where('codigo', '')->update(['codigo' => null]);
            Schema::table('inventario_items', function (Blueprint $table) {
                if (! $this->indexExists('inventario_items', 'inventario_items_codigo_unique')) {
                    $table->unique('codigo');
                }
            });
        }

        if (Schema::hasTable('inventario_movimientos')) {
            return;
        }

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_item_id')->constrained('inventario_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->string('tipo', 32);
            $table->string('nota', 400)->nullable();
            $table->timestamps();
            $table->index(['inventario_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
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
