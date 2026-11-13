<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulos de cada ciclo lectivo, cada uno con sus fechas: cuotas (marzo a febrero), académico
     * (marzo a diciembre) y reinscripción. Se completan los ciclos que ya existían.
     */
    public function up(): void
    {
        Schema::create('ciclo_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_lectivo_id')->constrained('ciclos_lectivos')->cascadeOnDelete();
            $table->string('clave', 20); // cuotas | academico | reinscripcion
            $table->date('inicio');
            $table->date('fin');
            $table->timestamps();
            $table->unique(['ciclo_lectivo_id', 'clave']);
        });

        foreach (DB::table('ciclos_lectivos')->get(['id', 'anio']) as $ciclo) {
            $anio = (int) $ciclo->anio;
            $fechas = [
                'cuotas' => [$anio.'-03-01', ($anio + 1).'-02-28'],
                'academico' => [$anio.'-03-01', $anio.'-12-15'],
                'reinscripcion' => [$anio.'-02-01', $anio.'-03-15'],
            ];
            foreach ($fechas as $clave => [$inicio, $fin]) {
                DB::table('ciclo_modulos')->insert([
                    'ciclo_lectivo_id' => $ciclo->id, 'clave' => $clave, 'inicio' => $inicio, 'fin' => $fin,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ciclo_modulos');
    }
};
