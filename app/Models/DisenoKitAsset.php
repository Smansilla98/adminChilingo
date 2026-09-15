<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisenoKitAsset extends Model
{
    protected $table = 'diseno_kit_assets';

    protected $fillable = [
        'titulo',
        'path',
        'mime',
        'bytes',
        'user_id',
    ];

    protected $casts = [
        'bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * @return array{id: string, label: string, kind: string, url: string, thumb: string, kit_id: int}
     */
    public function toBrandCatalogItem(): array
    {
        return [
            'id' => 'kit-'.$this->id,
            'label' => $this->titulo,
            'kind' => 'image',
            'url' => $this->url(),
            'thumb' => $this->url(),
            'kit_id' => $this->id,
        ];
    }
}
