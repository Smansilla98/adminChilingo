<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivo histórico fotográfico. Reutiliza sedes, eventos, shows, personas y
 * biblioteca_tags. Ver docs/ARCHIVO_HISTORICO.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archivo_fotos')) {
            return;
        }

        Schema::create('archivo_capitulos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 180);
            $table->string('slug', 200)->unique();
            $table->string('bajada', 300)->nullable();
            $table->text('descripcion')->nullable();
            $table->smallInteger('anio_desde')->nullable();
            $table->smallInteger('anio_hasta')->nullable();
            // Sin FK: la foto puede borrarse y el servicio limpia la referencia.
            $table->unsignedBigInteger('portada_foto_id')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('publicado')->default(false);
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();

            $table->index(['publicado', 'anio_desde']);
        });

        Schema::create('archivo_acontecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 180);
            $table->string('slug', 200)->unique();
            $table->date('fecha')->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('precision', 10)->default('anio'); // dia | mes | anio | aprox
            $table->string('bajada', 300)->nullable();
            $table->text('descripcion')->nullable();
            $table->longText('relato')->nullable();
            // Dónde ocurrió (preparado para un mapa futuro).
            $table->string('lugar', 180)->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('pais', 80)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->foreignId('capitulo_id')->nullable()->constrained('archivo_capitulos')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('evento_id')->nullable()->constrained('eventos')->nullOnDelete();
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->unsignedBigInteger('portada_foto_id')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('publicado')->default(false);
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();

            $table->index(['publicado', 'anio']);
            $table->index(['capitulo_id', 'orden']);
        });

        Schema::create('archivo_acontecimiento_relacion', function (Blueprint $table) {
            $table->foreignId('acontecimiento_id')->constrained('archivo_acontecimientos')->cascadeOnDelete();
            $table->foreignId('relacionado_id')->constrained('archivo_acontecimientos')->cascadeOnDelete();
            $table->primary(['acontecimiento_id', 'relacionado_id']);
        });

        Schema::create('archivo_fotos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 180)->nullable();
            $table->string('slug', 200)->unique();
            $table->text('descripcion')->nullable();     // epígrafe
            $table->text('contexto')->nullable();        // contexto histórico
            $table->text('notas_aportante')->nullable(); // lo que el aportante recuerda
            $table->string('alt_text', 300)->nullable();
            $table->string('tipo', 20)->nullable();      // ensayo | show | gira | ...

            $table->date('fecha')->nullable();
            $table->smallInteger('anio')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->string('precision', 10)->default('anio');
            $table->string('lugar', 180)->nullable();
            $table->string('ciudad', 120)->nullable();
            $table->string('pais', 80)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            // Autoría y procedencia: tres conceptos distintos.
            $table->string('fotografo', 150)->nullable();
            $table->string('fuente', 30)->nullable();
            $table->string('fuente_detalle', 255)->nullable();
            $table->string('credito', 200)->nullable();
            $table->string('licencia', 120)->nullable();
            $table->foreignId('aportada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('mostrar_aportante')->default(false);

            $table->foreignId('capitulo_id')->nullable()->constrained('archivo_capitulos')->nullOnDelete();
            $table->foreignId('acontecimiento_id')->nullable()->constrained('archivo_acontecimientos')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->boolean('destacada')->default(false);
            $table->unsignedInteger('orden')->default(0);

            // Moderación.
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('enviada_at')->nullable();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->text('notas_revision')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamp('publicada_at')->nullable();

            // Archivo original (conservación) y derivados web (rendimiento).
            $table->string('path', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->string('hash', 64)->nullable();
            $table->json('exif')->nullable();
            $table->json('derivados')->nullable();
            $table->text('placeholder')->nullable();
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->index(['estado', 'anio']);
            $table->index(['acontecimiento_id', 'orden']);
            $table->index(['capitulo_id', 'orden']);
            $table->index('hash');
            $table->index('aportada_por');
        });

        Schema::create('archivo_foto_tag', function (Blueprint $table) {
            $table->foreignId('archivo_foto_id')->constrained('archivo_fotos')->cascadeOnDelete();
            $table->foreignId('biblioteca_tag_id')->constrained('biblioteca_tags')->cascadeOnDelete();
            $table->primary(['archivo_foto_id', 'biblioteca_tag_id']);
        });

        Schema::create('archivo_foto_persona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_foto_id')->constrained('archivo_fotos')->cascadeOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('nombre', 150)->nullable(); // alguien que no está en el sistema
            $table->string('detalle', 150)->nullable(); // "tercera desde la izquierda"
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('persona_id');
            $table->index('nombre');
        });

        Schema::create('archivo_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_foto_id')->constrained('archivo_fotos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30);
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['archivo_foto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivo_revisiones');
        Schema::dropIfExists('archivo_foto_persona');
        Schema::dropIfExists('archivo_foto_tag');
        Schema::dropIfExists('archivo_fotos');
        Schema::dropIfExists('archivo_acontecimiento_relacion');
        Schema::dropIfExists('archivo_acontecimientos');
        Schema::dropIfExists('archivo_capitulos');
    }
};
