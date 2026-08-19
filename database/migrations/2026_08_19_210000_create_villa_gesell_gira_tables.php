<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('villa_gesell_config')) {
            Schema::create('villa_gesell_config', function (Blueprint $table) {
                $table->id();
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->unsignedSmallInteger('cupo_maximo')->default(40);
                $table->decimal('aporte_esperado', 12, 2)->default(0);
                $table->text('notas')->nullable();
                $table->timestamps();
            });
            DB::table('villa_gesell_config')->insert([
                'fecha_inicio' => '2027-01-16',
                'fecha_fin' => '2027-02-14',
                'cupo_maximo' => 40,
                'aporte_esperado' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('villa_gesell_inscriptos')) {
            Schema::create('villa_gesell_inscriptos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->string('estado_pago', 24)->default('pendiente');
                $table->decimal('monto_esperado', 12, 2)->default(0);
                $table->decimal('monto_pagado', 12, 2)->default(0);
                $table->unsignedSmallInteger('plaza')->nullable();
                $table->boolean('lista_espera')->default(false);
                $table->date('fecha_desde')->nullable();
                $table->date('fecha_hasta')->nullable();
                $table->string('talle_remera', 8)->nullable();
                $table->string('tambor_principal', 40)->nullable();
                $table->string('tambor_secundario', 40)->nullable();
                $table->string('tambor_terciario', 40)->nullable();
                $table->string('tambor_principal_origen', 20)->nullable();
                $table->string('tambor_secundario_origen', 20)->nullable();
                $table->string('tambor_terciario_origen', 20)->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->unique('alumno_id');
                $table->unique('plaza');
            });
        }

        if (! Schema::hasTable('villa_gesell_dias')) {
            Schema::create('villa_gesell_dias', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->string('notas', 400)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('villa_gesell_tocadas')) {
            Schema::create('villa_gesell_tocadas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dia_id')->constrained('villa_gesell_dias')->cascadeOnDelete();
                $table->unsignedTinyInteger('orden')->default(1);
                $table->time('hora')->nullable();
                $table->string('que', 160);
                $table->string('donde', 160)->nullable();
                $table->string('notas', 400)->nullable();
                $table->timestamps();
                $table->index(['dia_id', 'orden']);
            });
        }

        if (! Schema::hasTable('villa_gesell_insumos')) {
            Schema::create('villa_gesell_insumos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('categoria', 32);
                $table->decimal('cantidad', 10, 2)->default(1);
                $table->string('unidad', 20)->nullable();
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->text('notas')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('villa_gesell_gastos')) {
            Schema::create('villa_gesell_gastos', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 24);
                $table->string('concepto');
                $table->decimal('monto', 12, 2);
                $table->string('modo', 16)->default('total');
                $table->date('fecha')->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('villa_gesell_gastos');
        Schema::dropIfExists('villa_gesell_insumos');
        Schema::dropIfExists('villa_gesell_tocadas');
        Schema::dropIfExists('villa_gesell_dias');
        Schema::dropIfExists('villa_gesell_inscriptos');
        Schema::dropIfExists('villa_gesell_config');
    }
};
