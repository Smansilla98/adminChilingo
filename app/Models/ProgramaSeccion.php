<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaSeccion extends Model
{
    protected $table = 'programa_secciones';

    public const CAT_INTRO = 'intro';

    public const CAT_INSTITUCIONAL = 'institucional';

    public const CAT_ANIO = 'anio';

    public const CAT_RECURSOS = 'recursos';

    protected $fillable = [
        'slug',
        'titulo',
        'subtitulo',
        'cuerpo',
        'orden',
        'categoria',
        'anio',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'anio' => 'integer',
        'activo' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function categorias(): array
    {
        return [
            self::CAT_INTRO => 'Introducción',
            self::CAT_INSTITUCIONAL => 'Institucional',
            self::CAT_ANIO => 'Objetivos por año',
            self::CAT_RECURSOS => 'Recursos',
        ];
    }
}
