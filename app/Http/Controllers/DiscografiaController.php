<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Disco;
use App\Models\ProgramaRitmo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Discografía de la banda (pública) y su edición (administración).
 * Las portadas van al disco persistente `comprobantes` y salen por /portada.
 */
class DiscografiaController extends Controller
{
    private const DISCO_PORTADAS = 'comprobantes';

    public function index(): View
    {
        $discos = Disco::query()->publicados()->get();

        return view('programa.discografia.index', [
            'discos' => $discos,
            'toques' => $this->toquesDelPrograma(),
        ]);
    }

    public function show(Disco $disco): View
    {
        abort_unless($disco->publicado || auth()->user()?->isAdmin(), 404);
        $discos = Disco::query()->publicados()->get(['id', 'slug', 'titulo', 'anio']);
        $i = $discos->search(fn (Disco $d) => $d->id === $disco->id);

        return view('programa.discografia.show', [
            'disco' => $disco,
            'enPrograma' => $disco->toquesDelPrograma($this->toquesDelPrograma()),
            'anterior' => $i !== false && $i > 0 ? $discos[$i - 1] : null,
            'siguiente' => $i !== false && $i < $discos->count() - 1 ? $discos[$i + 1] : null,
        ]);
    }

    public function portada(Disco $disco): StreamedResponse
    {
        abort_unless($disco->publicado || auth()->user()?->isAdmin(), 404);
        $disk = Storage::disk(self::DISCO_PORTADAS);
        abort_unless($disco->portada_path && $disk->exists($disco->portada_path), 404);

        return $disk->response($disco->portada_path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Disco $disco): View
    {
        $this->autorizar();

        return view('programa.discografia.edit', ['disco' => $disco]);
    }

    public function update(Request $request, Disco $disco): RedirectResponse
    {
        $this->autorizar();
        $datos = $request->validate([
            'titulo' => 'required|string|max:255',
            'titulo_alternativo' => 'nullable|string|max:255',
            'anio' => 'required|integer|min:1995|max:'.(now()->year + 1),
            'nota_anio' => 'nullable|string|max:255',
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'descripcion' => 'nullable|string|max:5000',
            'datos' => 'nullable|string|max:3000',
            'temas' => 'nullable|string|max:10000',
            'nota_temas' => 'nullable|string|max:255',
            'enlaces' => 'nullable|string|max:3000',
            'fuentes' => 'nullable|string|max:3000',
            'portada' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'quitar_portada' => 'nullable|boolean',
            'publicado' => 'nullable|boolean',
        ], [
            'color.regex' => 'El color tiene que ser hexadecimal, como #c2410c.',
            'portada.image' => 'La portada tiene que ser una imagen (JPG, PNG o WebP).',
        ]);

        $enlaces = self::lineasConUrl($datos['enlaces'] ?? '');
        $fuentes = self::lineasConUrl($datos['fuentes'] ?? '');
        if ($enlaces === null || $fuentes === null) {
            return back()->withInput()->withErrors(['enlaces' => 'Cada enlace va en una línea: «Nombre | https://…».']);
        }

        $cambios = [
            'titulo' => trim($datos['titulo']),
            'titulo_alternativo' => self::texto($datos['titulo_alternativo'] ?? null),
            'anio' => (int) $datos['anio'],
            'nota_anio' => self::texto($datos['nota_anio'] ?? null),
            'color' => strtolower($datos['color']),
            'descripcion' => self::texto($datos['descripcion'] ?? null),
            'datos' => self::lineas($datos['datos'] ?? '') ?: null,
            'temas' => self::temas($datos['temas'] ?? '') ?: null,
            'nota_temas' => self::texto($datos['nota_temas'] ?? null),
            'enlaces' => $enlaces ?: null,
            'fuentes' => $fuentes ?: null,
            'publicado' => $request->boolean('publicado'),
        ];

        $disk = Storage::disk(self::DISCO_PORTADAS);
        if ($request->hasFile('portada')) {
            $nueva = $request->file('portada')->storeAs('discos', $disco->slug.'-'.Str::random(8).'.'.$request->file('portada')->extension(), self::DISCO_PORTADAS);
            if ($disco->portada_path) {
                $disk->delete($disco->portada_path);
            }
            $cambios['portada_path'] = $nueva;
        } elseif ($request->boolean('quitar_portada') && $disco->portada_path) {
            $disk->delete($disco->portada_path);
            $cambios['portada_path'] = null;
        }

        $antes = $disco->only(array_keys($cambios));
        $disco->update($cambios);
        $despues = $disco->only(array_keys($cambios));
        $distintos = array_filter($despues, fn ($v, $k) => $antes[$k] !== $v, ARRAY_FILTER_USE_BOTH);
        if ($distintos !== []) {
            Auditoria::registrar('discografia.disco.editado', $disco, array_intersect_key($antes, $distintos), $distintos);
        }

        return redirect()->route('programa.discos.show', $disco)->with('success', "«{$disco->titulo}» quedó actualizado.");
    }

    /** @return Collection<int, ProgramaRitmo> */
    private function toquesDelPrograma(): Collection
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return collect();
        }
        $q = ProgramaRitmo::soloEnPrograma(ProgramaRitmo::query()->select(['id', 'slug', 'nombre', 'año', 'orden']));
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where('publicado', true);
        }

        return $q->get();
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private static function texto(?string $s): ?string
    {
        $s = trim((string) $s);

        return $s !== '' ? $s : null;
    }

    /** @return list<string> */
    private static function lineas(string $texto): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $texto) ?: []), fn ($l) => $l !== ''));
    }

    /**
     * "Sacateca — 1:53" / "Sacateca 1:53" / "Sacateca" → tema con duración opcional.
     *
     * @return list<array{titulo: string, duracion: string|null}>
     */
    public static function temas(string $texto): array
    {
        return array_map(function (string $linea) {
            $linea = (string) preg_replace('/^\d{1,2}[.)]\s*/', '', $linea);
            if (preg_match('/^(.*?)[\s\-—–|·]+(\d{1,2}:[0-5]\d)$/u', $linea, $m) && trim($m[1]) !== '') {
                return ['titulo' => trim($m[1]), 'duracion' => $m[2]];
            }

            return ['titulo' => $linea, 'duracion' => null];
        }, self::lineas($texto));
    }

    /**
     * "Spotify | https://…" por línea. Null si alguna línea no tiene una URL válida.
     *
     * @return list<array{etiqueta: string, url: string}>|null
     */
    public static function lineasConUrl(string $texto): ?array
    {
        $out = [];
        foreach (self::lineas($texto) as $linea) {
            [$etiqueta, $url] = str_contains($linea, '|') ? array_map('trim', explode('|', $linea, 2)) : ['', trim($linea)];
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
                return null;
            }
            $out[] = ['etiqueta' => $etiqueta !== '' ? $etiqueta : (string) parse_url($url, PHP_URL_HOST), 'url' => $url];
        }

        return $out;
    }
}
