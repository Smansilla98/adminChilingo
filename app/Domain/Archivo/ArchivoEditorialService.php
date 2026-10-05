<?php

namespace App\Domain\Archivo;

use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Capítulos y acontecimientos del archivo: el relato que ordena las fotos.
 */
class ArchivoEditorialService
{
    public function __construct(private readonly ArchivoService $fotos) {}

    /** @return array<string, mixed> */
    public function reglasCapitulo(): array
    {
        $max = (int) now()->year;

        return [
            'titulo' => 'required|string|max:180',
            'bajada' => 'nullable|string|max:300',
            'descripcion' => 'nullable|string|max:8000',
            'anio_desde' => "nullable|integer|between:1900,{$max}",
            'anio_hasta' => "nullable|integer|between:1900,{$max}|gte:anio_desde",
            'portada_foto_id' => 'nullable|integer|exists:archivo_fotos,id',
            'publicado' => 'nullable|boolean',
        ];
    }

    /** @return array<string, mixed> */
    public function reglasAcontecimiento(): array
    {
        $max = (int) now()->year;

        return [
            'titulo' => 'required|string|max:180',
            'fecha' => 'nullable|date',
            'anio' => "nullable|integer|between:1900,{$max}",
            'precision' => ['nullable', Rule::in(array_keys(ArchivoFoto::PRECISIONES))],
            'bajada' => 'nullable|string|max:300',
            'descripcion' => 'nullable|string|max:3000',
            'relato' => 'nullable|string|max:30000',
            'lugar' => 'nullable|string|max:180',
            'ciudad' => 'nullable|string|max:120',
            'pais' => 'nullable|string|max:80',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'capitulo_id' => 'nullable|integer|exists:archivo_capitulos,id',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'evento_id' => 'nullable|integer|exists:eventos,id',
            'show_id' => 'nullable|integer|exists:shows,id',
            'portada_foto_id' => 'nullable|integer|exists:archivo_fotos,id',
            'publicado' => 'nullable|boolean',
            'relacionados' => 'nullable|array|max:20',
            'relacionados.*' => 'integer|exists:archivo_acontecimientos,id',
        ];
    }

    /** @param  array<string, mixed>  $datos */
    public function guardarCapitulo(?ArchivoCapitulo $capitulo, array $datos): ArchivoCapitulo
    {
        $capitulo ??= new ArchivoCapitulo(['orden' => (int) ArchivoCapitulo::query()->max('orden') + 1]);
        $publicado = (bool) ($datos['publicado'] ?? false);
        $capitulo->fill([
            'titulo' => trim($datos['titulo']),
            'bajada' => $datos['bajada'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'anio_desde' => $datos['anio_desde'] ?? null,
            'anio_hasta' => $datos['anio_hasta'] ?? null,
            'portada_foto_id' => $datos['portada_foto_id'] ?? null,
            'publicado' => $publicado,
        ]);
        if ($publicado && ! $capitulo->publicado_at) {
            $capitulo->publicado_at = now();
        }
        if (! $capitulo->exists || $capitulo->isDirty('titulo')) {
            $capitulo->slug = $this->fotos->slugUnico(ArchivoCapitulo::class, $capitulo->titulo, $capitulo->id);
        }
        $capitulo->save();

        return $capitulo;
    }

    public function eliminarCapitulo(ArchivoCapitulo $capitulo): void
    {
        // Las fotos y acontecimientos quedan sin capítulo (FK nullOnDelete), no se borran.
        $capitulo->delete();
    }

    /** @param  array<string, mixed>  $datos */
    public function guardarAcontecimiento(?ArchivoAcontecimiento $a, array $datos): ArchivoAcontecimiento
    {
        if (empty($datos['anio']) && empty($datos['fecha'])) {
            throw ValidationException::withMessages(['anio' => 'Indicá el año (o la fecha) del acontecimiento.']);
        }

        return DB::transaction(function () use ($a, $datos) {
            $a ??= new ArchivoAcontecimiento(['orden' => (int) ArchivoAcontecimiento::query()->max('orden') + 1]);
            $publicado = (bool) ($datos['publicado'] ?? false);
            $campos = ['titulo', 'fecha', 'anio', 'precision', 'bajada', 'descripcion', 'relato', 'lugar', 'ciudad', 'pais',
                'latitud', 'longitud', 'capitulo_id', 'sede_id', 'evento_id', 'show_id', 'portada_foto_id'];
            foreach ($campos as $c) {
                if (array_key_exists($c, $datos)) {
                    $a->{$c} = is_string($datos[$c]) ? (trim($datos[$c]) ?: null) : $datos[$c];
                }
            }
            if ($a->fecha) {
                $a->anio = $a->fecha->year;
                $a->precision = $a->precision && $a->precision !== 'anio' ? $a->precision : 'dia';
            }
            $a->precision ??= 'anio';
            $a->publicado = $publicado;
            if ($publicado && ! $a->publicado_at) {
                $a->publicado_at = now();
            }
            if (! $a->exists || $a->isDirty('titulo')) {
                $a->slug = $this->fotos->slugUnico(ArchivoAcontecimiento::class, $a->titulo, $a->id);
            }
            $a->save();

            if (array_key_exists('relacionados', $datos)) {
                $ids = collect($datos['relacionados'] ?? [])->map('intval')->reject(fn ($id) => $id === $a->id)->unique()->values();
                $a->relacionados()->sync($ids);
                // Relación simétrica: si A cita a B, B muestra a A.
                foreach ($ids as $id) {
                    ArchivoAcontecimiento::query()->find($id)?->relacionados()->syncWithoutDetaching([$a->id]);
                }
            }

            return $a;
        });
    }

    public function eliminarAcontecimiento(ArchivoAcontecimiento $a): void
    {
        $a->delete();
    }

    /**
     * @param  class-string<ArchivoCapitulo|ArchivoAcontecimiento>  $modelo
     * @param  list<int>  $ids
     */
    public function ordenar(string $modelo, array $ids): void
    {
        DB::transaction(function () use ($modelo, $ids) {
            foreach (array_values($ids) as $i => $id) {
                $modelo::query()->whereKey($id)->update(['orden' => $i + 1]);
            }
        });
    }
}
