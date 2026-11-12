<?php

namespace App\Support;

use App\Models\ProgramaRitmo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Grabaciones de referencia de los toques (audio_ref de la partitura).
 *
 * Se guardan en el disco de archivos como partituras/audio/{id del toque}-{azar}.{ext}:
 * el prefijo con el id permite servir la grabación de un borrador o de una versión
 * vieja sin abrir la puerta a otros archivos del disco.
 */
final class PartituraAudio
{
    public const EXTENSIONES = ['mp3', 'wav', 'm4a', 'ogg'];

    /** Tope de subida en KB (50 MB). */
    public const MAX_KB = 51200;

    /** Disco persistente: `archivos` si existe (equipo de infraestructura), si no `comprobantes`. */
    public static function disco(): string
    {
        return config('filesystems.disks.archivos') ? 'archivos' : 'comprobantes';
    }

    /** @return array<int, mixed> */
    public static function reglas(): array
    {
        return ['required', 'file', 'extensions:'.implode(',', self::EXTENSIONES), 'max:'.self::MAX_KB, ArchivoSeguro::regla()];
    }

    /** @return array<string, string> */
    public static function mensajes(string $campo = 'audio'): array
    {
        return [
            $campo.'.required' => 'Elegí una grabación (MP3, WAV, M4A u OGG).',
            $campo.'.extensions' => 'La grabación tiene que ser MP3, WAV, M4A u OGG.',
            $campo.'.max' => 'La grabación no puede pasar de 50 MB.',
        ];
    }

    /**
     * Guarda la grabación y devuelve un audio_ref nuevo (sin pulsos marcados).
     *
     * @return array<string, mixed>
     */
    public static function guardar(UploadedFile $archivo, ProgramaRitmo $toque): array
    {
        $ext = strtolower($archivo->getClientOriginalExtension());
        $ext = in_array($ext, self::EXTENSIONES, true) ? $ext : 'mp3';
        $nombre = $toque->id.'-'.Str::lower(Str::random(16)).'.'.$ext;
        $archivo->storeAs(PartituraScore::AUDIO_CARPETA, $nombre, self::disco());

        return (array) PartituraScore::normalizarAudioRef([
            'path' => PartituraScore::AUDIO_CARPETA.'/'.$nombre,
            'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 120),
            'offset_seg' => 0,
            'beats' => [],
        ]);
    }

    /** Ruta en el disco del archivo pedido, si es una grabación de este toque y existe. */
    public static function rutaDe(ProgramaRitmo $toque, string $archivo): ?string
    {
        if (! preg_match('/^'.$toque->id.'-[a-z0-9]{16}\.('.implode('|', self::EXTENSIONES).')$/', $archivo)) {
            return null;
        }
        $ruta = PartituraScore::AUDIO_CARPETA.'/'.$archivo;

        return \Illuminate\Support\Facades\Storage::disk(self::disco())->exists($ruta) ? $ruta : null;
    }

    public static function responder(ProgramaRitmo $toque, string $archivo): StreamedResponse
    {
        $ruta = self::rutaDe($toque, $archivo);
        abort_unless($ruta !== null, 404, 'No está esa grabación.');

        return ArchivoSeguro::responder(self::disco(), $ruta, $toque->slug.'.'.pathinfo($ruta, PATHINFO_EXTENSION), true, [
            'Cache-Control' => 'private, max-age=86400',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
