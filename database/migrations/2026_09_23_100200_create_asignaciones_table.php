<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignaciones explícitas de rol o permiso con alcance (global / sede / bloque).
 * Los roles académicos (alumno, profesor, coordinador por profesor_sede) se derivan
 * de sus tablas y no se duplican acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asignaciones')) {
            return;
        }

        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->cascadeOnDelete();
            $table->string('ambito_tipo', 16)->default('global'); // global | sede | bloque
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->cascadeOnDelete();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->cascadeOnDelete();
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('notas', 500)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['persona_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
