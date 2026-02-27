<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un alumno puede pertenecer a varios bloques a la vez.
     * Se mantiene alumno.bloque_id como "bloque principal" (opcional).
     */
    public function up(): void
    {
        Schema::create('alumno_bloque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('bloque_id')->constrained('bloques')->onDelete('cascade');
            $table->boolean('es_principal')->default(false)->comment('Si es el bloque principal del alumno');
            $table->timestamps();

            $table->unique(['alumno_id', 'bloque_id']);
        });

        // Migrar: cada alumno que tiene bloque_id pasa a la pivot (y marcar como principal)
        $alumnos = \DB::table('alumnos')->whereNotNull('bloque_id')->get();
        foreach ($alumnos as $a) {
            \DB::table('alumno_bloque')->insert([
                'alumno_id' => $a->id,
                'bloque_id' => $a->bloque_id,
                'es_principal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_bloque');
    }
};
