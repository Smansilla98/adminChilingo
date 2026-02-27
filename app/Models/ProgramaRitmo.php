<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaRitmo extends Model
{
    protected $fillable = ['año', 'orden', 'nombre', 'autor', 'opcional', 'notas'];

    protected $casts = [
        'año' => 'integer',
        'orden' => 'integer',
        'opcional' => 'boolean',
    ];

    public static function años(): array
    {
        return [1 => '1° Año', 2 => '2° Año', 3 => '3° Año', 4 => '4° Año', 5 => '5° Año', 6 => '6° Año'];
    }
}
