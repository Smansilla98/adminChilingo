<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class BusinessSchema
{
    public static function migrateMinimal(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('pago_detalles');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('comprobante_cuota_alumno_items');
        Schema::dropIfExists('comprobantes_cuota_alumnos');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('cuota_alumno');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('alumno_bloque');
        Schema::dropIfExists('bloque_profesor');
        Schema::dropIfExists('alumnos');
        Schema::dropIfExists('bloques');
        Schema::dropIfExists('profesores');
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->json('modulos_access')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('coordinador_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('profesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('bloques', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellido');
            $table->string('dni')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('instrumento_principal')->nullable();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('monto', 12, 2);
            $table->unsignedTinyInteger('mes')->nullable();
            $table->unsignedSmallInteger('año')->nullable();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->string('alcance')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_pago');
            $table->decimal('monto_total', 12, 2);
            $table->string('comprobante_path')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->decimal('abono_profesor', 12, 2)->nullable();
            $table->decimal('abono_base', 12, 2)->nullable();
            $table->decimal('abono_porcentaje', 10, 4)->nullable();
            $table->string('abono_nota', 500)->nullable();
            $table->timestamps();
            $table->unique(['pago_id', 'alumno_id', 'cuota_id']);
        });

        Schema::create('comprobantes_cuota_alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->string('comprobante_path')->nullable();
            $table->text('notas')->nullable();
            $table->string('estado')->default('pendiente');
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->foreignId('cargado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('comprobante_cuota_alumno_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_cuota_alumno_id');
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('bloque_id')->constrained('bloques')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo_asistencia')->nullable();
            $table->boolean('presente')->default(false);
            $table->timestamps();
            $table->unique(['alumno_id', 'bloque_id', 'fecha']);
        });

        foreach (['admin', 'direccion', 'coordinador_sede', 'coordinador_area', 'profesor', 'alumno'] as $rol) {
            Role::findOrCreate($rol);
        }
    }
}
