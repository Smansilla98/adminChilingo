<?php

namespace App\Domain\Biblioteca;

use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Models\ProgramaRitmo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Biblioteca de materiales (fotos, videos, audios, PDF, enlaces) con hashtags y, si
 * corresponde, toque e instrumento del programa. Web y API publican y moderan igual.
 */
class BibliotecaService
{
    public const ARCHIVO_MAX_KB = 102400;

    public const ARCHIVO_EXTENSIONES = 'jpg,jpeg,png,webp,gif,mp4,m4v,webm,mov,mp3,wav,ogg,m4a,pdf';

    /** @return array<string, mixed> */
    public function reglas(): array
    {
        $reglas = [
            'titulo' => 'required|string|max:180',
            'descripcion' => 'nullable|string|max:2000',
            'autor_nombre' => 'nullable|string|max:120',
            'hashtags' => 'nullable|string|max:400',
            'url' => 'nullable|url|max:500',
            'archivo' => 'nullable|file|max:'.self::ARCHIVO_MAX_KB.'|extensions:'.self::ARCHIVO_EXTENSIONES,
            'instrumento' => ['nullable', 'string', Rule::in(array_keys(BibliotecaItem::instrumentosOpciones()))],
        ];
        if (Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
            $reglas['toque'] = ['nullable', 'string', 'max:120', Rule::exists('programa_ritmos', 'slug')];
        }

        return $reglas;
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'archivo.uploaded' => 'No se pudo subir el archivo. Si es un video, suele ser demasiado grande o se cortó la conexión. Máximo 100 MB, o pegá un enlace.',
            'archivo.max' => 'El archivo no puede superar 100 MB.',
            'archivo.extensions' => 'Formatos permitidos: PNG/JPG/WebP, MP4/WebM/MOV, audio o PDF.',
            'toque.exists' => 'Elegí un toque válido del programa.',
            'instrumento.in' => 'Elegí un instrumento de la lista.',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglas()
     */
    public function publicar(array $datos, ?UploadedFile $archivo, ?string $ip): BibliotecaItem
    {
        if (empty($datos['url']) && ! $archivo) {
            throw ValidationException::withMessages(['archivo' => 'Subí un archivo o pegá un enlace.']);
        }
        if (! empty($datos['instrumento']) && empty($datos['toque'] ?? null)) {
            throw ValidationException::withMessages(['toque' => 'Para indicar instrumento, elegí también el toque.']);
        }

        $path = $mime = $nombreOriginal = $bytes = $ext = null;
        if ($archivo) {
            $ext = strtolower((string) $archivo->getClientOriginalExtension());
            if ($ext === '' && $archivo->guessExtension()) {
                $ext = strtolower((string) $archivo->guessExtension());
            }
            $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();
            if (($mime === 'application/octet-stream' || ! $mime) && $ext === 'png') {
                $mime = 'image/png';
            }
            if (($mime === 'application/octet-stream' || ! $mime) && in_array($ext, ['mp4', 'm4v', 'mov'], true)) {
                $mime = $ext === 'mov' ? 'video/quicktime' : 'video/mp4';
            }
            $nombreOriginal = $archivo->getClientOriginalName();
            $bytes = $archivo->getSize();
            $filename = (string) Str::uuid().($ext !== '' ? '.'.$ext : '');
            $dir = 'biblioteca/'.now()->format('Y/m');
            try {
                $guardado = $archivo->storeAs($dir, $filename, 'comprobantes');
            } catch (\Throwable $e) {
                report($e);
                $guardado = false;
            }
            if (! $guardado) {
                throw ValidationException::withMessages(['archivo' => 'No se pudo guardar el archivo en el servidor. Reintentá o pegá un enlace.']);
            }
            $path = $dir.'/'.$filename;
        }

        $payload = [
            'titulo' => trim($datos['titulo']),
            'descripcion' => isset($datos['descripcion']) ? trim($datos['descripcion']) : null,
            'tipo' => BibliotecaItem::detectarTipo($mime, $ext, ! empty($datos['url'])),
            'path' => $path,
            'url' => $datos['url'] ?? null,
            'mime' => $mime,
            'nombre_original' => $nombreOriginal,
            'bytes' => $bytes,
            'autor_nombre' => isset($datos['autor_nombre']) ? trim($datos['autor_nombre']) : null,
            'estado' => 'publicado',
            'ip' => $ip,
        ];
        if (Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
            $toqueId = ! empty($datos['toque']) ? ProgramaRitmo::query()->where('slug', $datos['toque'])->value('id') : null;
            $payload['programa_ritmo_id'] = $toqueId;
            $payload['instrumento'] = $toqueId ? ($datos['instrumento'] ?? null) : null;
        }

        $item = BibliotecaItem::create($payload);
        $tags = BibliotecaTag::syncFromInput($datos['hashtags'] ?? '');
        if ($tags !== []) {
            $item->tags()->sync(collect($tags)->pluck('id')->all());
            foreach ($tags as $tag) {
                $tag->increment('usos');
            }
        }

        return $item;
    }

    public function alternarVisibilidad(BibliotecaItem $item): BibliotecaItem
    {
        $item->estado = $item->estado === 'publicado' ? 'oculto' : 'publicado';
        $item->save();

        return $item;
    }

    public function eliminar(BibliotecaItem $item): void
    {
        $tagIds = $item->tags()->pluck('biblioteca_tags.id');
        if ($item->path && Storage::disk('comprobantes')->exists($item->path)) {
            Storage::disk('comprobantes')->delete($item->path);
        }
        $item->tags()->detach();
        $item->delete();
        if (Schema::hasTable('biblioteca_tags') && $tagIds->isNotEmpty()) {
            BibliotecaTag::query()->whereIn('id', $tagIds)->each(function (BibliotecaTag $tag) {
                $tag->usos = max(0, (int) $tag->items()->count());
                $tag->save();
            });
        }
    }
}
