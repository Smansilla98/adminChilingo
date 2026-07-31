<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class BibliotecaTag extends Model
{
    protected $fillable = [
        'nombre',
        'slug',
        'usos',
    ];

    protected $casts = [
        'usos' => 'integer',
    ];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(BibliotecaItem::class, 'biblioteca_item_tag');
    }

    public static function normalizarNombre(string $raw): string
    {
        $t = trim($raw);
        $t = ltrim($t, "# \t");
        $t = preg_replace('/\s+/u', '', $t) ?? '';
        $t = mb_strtolower($t);

        return mb_substr($t, 0, 80);
    }

    public static function slugFromNombre(string $nombre): string
    {
        $slug = Str::slug($nombre, '-');
        if ($slug === '') {
            $slug = 'tag-'.substr(md5($nombre), 0, 8);
        }

        return mb_substr($slug, 0, 80);
    }

    /**
     * @param  list<string>|string  $raw
     * @return list<self>
     */
    public static function syncFromInput(array|string $raw): array
    {
        if (is_string($raw)) {
            preg_match_all('/#?([\p{L}\p{N}_-]{2,40})/u', $raw, $m);
            $parts = $m[1] ?? [];
        } else {
            $parts = $raw;
        }

        $tags = [];
        $seen = [];
        foreach ($parts as $part) {
            $nombre = self::normalizarNombre((string) $part);
            if ($nombre === '' || isset($seen[$nombre])) {
                continue;
            }
            $seen[$nombre] = true;
            $slug = self::slugFromNombre($nombre);
            $tag = self::query()->firstOrCreate(
                ['slug' => $slug],
                ['nombre' => $nombre, 'usos' => 0]
            );
            $tags[] = $tag;
            if (count($tags) >= 12) {
                break;
            }
        }

        return $tags;
    }
}
