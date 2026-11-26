<?php

namespace App\Domain\Disenos;

use App\Models\Diseno;
use App\Models\DisenoPagina;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Altas, cambios y páginas de los diseños del editor. Las validaciones de entrada quedan en el controlador.
 */
class GestorDiseno
{
    /** Tamaños con nombre (se guardan en `formato` para reportes y compatibilidad). */
    public const FORMATOS = [
        '1080x1350' => 'flyer_feed',
        '1080x1920' => 'historia',
        '1080x1080' => 'post_cuadrado',
        '1240x1754' => 'afiche_a4',
        '874x1240' => 'flyer_a5',
        '1200x628' => 'banner_web',
        '2362x4724' => 'hoodie_20x40',
        '1181x1181' => 'parche_10x10',
    ];

    /** @param array<string, mixed> $data */
    public function crear(array $data, ?array $canvas, int $ancho, int $alto, int $userId): Diseno
    {
        return DB::transaction(function () use ($data, $canvas, $ancho, $alto, $userId) {
            $diseno = Diseno::query()->create([
                'titulo' => $data['name'] ?? 'Diseño sin título',
                'formato' => self::FORMATOS[$ancho.'x'.$alto] ?? 'custom',
                'ancho' => $ancho,
                'alto' => $alto,
                'canvas_json' => $canvas,
                'user_id' => $userId,
            ]);
            $diseno->paginas()->create([
                'titulo' => 'Página 1',
                'canvas_json' => $canvas ? json_encode($canvas) : '{}',
                'orden' => 0,
            ]);

            return $diseno;
        });
    }

    /** Aplica sólo los campos presentes en la petición. */
    public function aplicarCambios(Diseno $diseno, array $data, ?array $canvas): Diseno
    {
        $cambios = [];
        if (array_key_exists('name', $data) && filled($data['name'])) {
            $cambios['titulo'] = $data['name'];
        }
        if (array_key_exists('canvas_json', $data) && $data['canvas_json'] !== null) {
            $cambios['canvas_json'] = $canvas;
        }
        if (! empty($data['width']) && ! empty($data['height'])) {
            $cambios['ancho'] = (int) $data['width'];
            $cambios['alto'] = (int) $data['height'];
            $cambios['formato'] = self::FORMATOS[$data['width'].'x'.$data['height']] ?? 'custom';
        }
        if (! empty($data['thumbnail_data'])) {
            $cambios['preview_path'] = app(CatalogoDiseno::class)->guardarMiniatura($diseno, $data['thumbnail_data']) ?? $diseno->preview_path;
        }
        $diseno->fill($cambios)->save();

        return $diseno;
    }

    /** Página nueva al final, o justo después de `after_sort_order` (desplaza las siguientes). */
    public function agregarPagina(Diseno $diseno, array $data): DisenoPagina
    {
        return DB::transaction(function () use ($diseno, $data) {
            if (isset($data['after_sort_order'])) {
                $diseno->paginas()->where('orden', '>', $data['after_sort_order'])->increment('orden');
                $orden = $data['after_sort_order'] + 1;
            } else {
                $orden = (int) ($diseno->paginas()->max('orden') ?? -1) + 1;
            }

            return $diseno->paginas()->create([
                'titulo' => $data['title'] ?? 'Página '.($diseno->paginas()->count() + 1),
                'canvas_json' => $data['canvas_json'] ?? '{}',
                'orden' => $orden,
            ]);
        });
    }

    public function duplicarPagina(DisenoPagina $pagina): DisenoPagina
    {
        return DB::transaction(function () use ($pagina) {
            DisenoPagina::query()->where('diseno_id', $pagina->diseno_id)->where('orden', '>', $pagina->orden)->increment('orden');

            return DisenoPagina::query()->create([
                'diseno_id' => $pagina->diseno_id,
                'titulo' => Str::limit($pagina->titulo.' (copia)', 120, ''),
                'canvas_json' => $pagina->canvas_json,
                'orden' => $pagina->orden + 1,
            ]);
        });
    }

    /** Actualiza la página; si es la primera del diseño, regenera la miniatura. */
    public function actualizarPagina(DisenoPagina $pagina, array $data, ?array $canvas): DisenoPagina
    {
        if (isset($data['canvas_json'])) {
            $pagina->canvas_json = $data['canvas_json'];
        }
        if (filled($data['title'] ?? null)) {
            $pagina->titulo = $data['title'];
        }
        $pagina->save();
        $pagina->diseno->touch();
        $primera = $pagina->diseno->paginas()->orderBy('orden')->value('id');
        if (isset($data['canvas_json']) && (int) $primera === (int) $pagina->id) {
            app(MiniaturaDiseno::class)->guardar($pagina->diseno, $data['canvas_json']);
        }

        return $pagina;
    }
}
