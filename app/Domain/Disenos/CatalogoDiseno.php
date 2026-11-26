<?php

namespace App\Domain\Disenos;

use App\Models\BibliotecaItem;
use App\Models\Diseno;
use App\Models\DisenoKitAsset;
use App\Support\MarcaChilinga;
use App\Support\MediosDiseno;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo del editor de diseño: plantillas, pestaña "Marca" y procesado del lienzo.
 * El controlador DisenoEditorController sólo valida, autoriza y responde.
 */
class CatalogoDiseno
{
    /**
     * Plantillas de La Chilinga + generales (database/data/disenos-plantillas.json) y
     * plantillas de estudio: diseños sin dueño del editor anterior (se copian, no se editan).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function plantillas(): Collection
    {
        $fijas = collect(json_decode((string) file_get_contents(database_path('data/disenos-plantillas.json')), true) ?: [])
            ->map(fn (array $t) => $t + ['thumbnail_url' => null]);

        $estudio = Diseno::query()->whereNull('user_id')->latest()->limit(50)->get()->map(fn (Diseno $d) => [
            'id' => 'estudio-'.$d->id,
            'name' => $d->titulo,
            'category' => 'estudio',
            'canvas_json' => $d->canvas_json ? json_encode($d->canvas_json) : '{}',
            'width' => (int) $d->ancho,
            'height' => (int) $d->alto,
            'thumbnail_url' => $d->paraEditor()['thumbnail_url'],
            'sort_order' => 50,
        ]);

        return $fijas->concat($estudio)->sortBy('sort_order');
    }

    /** Pestaña "Marca": logos, kit de marca, mockups y fotos de la Biblioteca. */
    public function grupoMarca(): array
    {
        // Primero las piezas del manual vigente; después los logos anteriores.
        $logos = collect(MarcaChilinga::LOGOS)
            ->filter(fn ($label, $archivo) => is_file(public_path("images/brand/v3/$archivo.png")))
            ->map(fn ($label, $archivo) => ['id' => "v3-$archivo", 'url' => asset("images/brand/v3/$archivo.png"), 'label' => $label])
            ->values()
            ->push(['id' => 'logo-30', 'url' => asset('images/brand/chilinga-30.png'), 'label' => 'La Chilinga 30 años'])
            ->push(['id' => 'logo', 'url' => asset('images/brand/logo.png'), 'label' => 'Logo ITO']);
        $mockups = collect([
            'remera-tinta.png' => 'Remera tinta', 'remera-hueso.png' => 'Remera hueso', 'camiseta-cielo.png' => 'Camiseta cielo',
            'hoodie.svg' => 'Mockup hoodie', 'campera.svg' => 'Mockup campera', 'jersey.svg' => 'Mockup jersey', 'tote.svg' => 'Mockup tote',
        ])
            ->filter(fn ($label, $archivo) => is_file(public_path("images/diseno/mockups/$archivo")))
            ->map(fn ($label, $archivo) => ['id' => 'mockup-'.pathinfo($archivo, PATHINFO_FILENAME), 'url' => asset("images/diseno/mockups/$archivo"), 'label' => $label])
            ->values();

        $kit = Schema::hasTable('diseno_kit_assets')
            ? DisenoKitAsset::query()->latest()->limit(100)->get()->map(fn (DisenoKitAsset $a) => [
                'id' => 'kit-'.$a->id, 'url' => $a->url(), 'label' => $a->titulo, 'kit_id' => $a->id,
            ])->values()
            : collect();

        // Solo archivos propios (mismo dominio): una URL externa impediría exportar el PNG.
        $biblioteca = BibliotecaItem::query()->publicados()->whereNotNull('path')->latest()->limit(60)->get()
            ->filter(fn (BibliotecaItem $i) => $i->esImagen())
            ->map(fn (BibliotecaItem $i) => ['id' => 'bib-'.$i->id, 'url' => $i->archivoUrl(), 'label' => $i->titulo ?: 'Foto'])
            ->values();

        return [
            'manual' => is_file(MarcaChilinga::manualPath()) ? [
                'version' => MarcaChilinga::VERSION,
                'url' => route('disenos.manual'),
                'paleta' => MarcaChilinga::PALETA,
                'sedes' => MarcaChilinga::SEDES,
            ] : null,
            'grupos' => [
                ['clave' => 'logos', 'titulo' => 'Logos', 'items' => $logos],
                ['clave' => 'kit', 'titulo' => 'Kit de marca', 'items' => $kit],
                ['clave' => 'mockups', 'titulo' => 'Mockups', 'items' => $mockups],
                ['clave' => 'biblioteca', 'titulo' => 'Biblioteca', 'items' => $biblioteca],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function decodificarLienzo(?string $json): ?array
    {
        if ($json === null || trim($json) === '' || trim($json) === '{}') {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw ValidationException::withMessages(['canvas_json' => 'El diseño recibido no es válido.']);
        }

        return $data;
    }

    /** Guarda la miniatura enviada como data URL; devuelve la ruta o null si no es una imagen válida. */
    public function guardarMiniatura(Diseno $diseno, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(jpeg|png);base64,(.+)$#', $dataUrl, $m)) {
            return null;
        }
        $binario = base64_decode($m[2], true);
        if ($binario === false || strlen($binario) > 1_500_000 || @getimagesizefromstring($binario) === false) {
            return null;
        }
        $path = 'disenos/previews/'.$diseno->id.'.'.($m[1] === 'png' ? 'png' : 'jpg');
        if ($diseno->preview_path && $diseno->preview_path !== $path) {
            MediosDiseno::borrar($diseno->preview_path);
        }
        MediosDiseno::guardar($path, $binario);

        return $path;
    }
}
