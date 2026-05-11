<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profesores vinculados a bloques con rol (titular, ayudante, etc.).
     * Se migra desde bloques.profesor_id como fila titular.
     */
    public function up(): void
    {
        if (Schema::hasTable('bloque_profesor')) {
            return;
        }

        Schema::create('bloque_profesor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bloque_id')->constrained('bloques')->cascadeOnDelete();
            $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
            $table->string('rol', 40)->default('titular');
            $table->timestamps();
            $table->unique(['bloque_id', 'profesor_id']);
        });

        if (!Schema::hasTable('bloques') || !Schema::hasColumn('bloques', 'profesor_id')) {
            return;
        }

        $now = now();
        $rows = DB::table('bloques')->whereNotNull('profesor_id')->select('id', 'profesor_id')->get();
        foreach ($rows as $b) {
            DB::table('bloque_profesor')->insertOrIgnore([
                'bloque_id' => $b->id,
                'profesor_id' => $b->profesor_id,
                'rol' => 'titular',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bloque_profesor');
    }
};
