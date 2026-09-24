<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notificaciones internas (tabla estándar de Laravel).
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Dispositivos para push (token Expo por instalación de la app).
        if (! Schema::hasTable('dispositivos')) {
            Schema::create('dispositivos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token', 255)->unique();
                $table->string('plataforma', 16)->nullable(); // android | ios
                $table->string('nombre', 120)->nullable();
                $table->timestamp('ultimo_uso_at')->nullable();
                $table->timestamps();
            });
        }

        // Deduplicación de envíos: una clave de negocio por canal se envía una sola vez.
        if (! Schema::hasTable('notificacion_envios')) {
            Schema::create('notificacion_envios', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 191)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('canal', 20);
                $table->string('estado', 20)->default('enviado');
                $table->timestamps();
            });
        }

        // Idempotencia de operaciones hechas offline desde la app.
        if (! Schema::hasTable('sync_operaciones')) {
            Schema::create('sync_operaciones', function (Blueprint $table) {
                $table->id();
                $table->uuid('client_uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tipo', 40);
                $table->json('respuesta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operaciones');
        Schema::dropIfExists('notificacion_envios');
        Schema::dropIfExists('dispositivos');
        Schema::dropIfExists('notifications');
    }
};
