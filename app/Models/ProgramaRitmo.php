<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaRitmo extends Model
{
    protected $table = 'programa_ritmos';

    protected $fillable = [
        'slug',
        'año',
        'orden',
        'nombre',
        'autor',
        'opcional',
        'notas',
        'resumen',
        'contenido',
        'secciones',
        'enlaces',
        'publicado',
    ];

    protected $casts = [
        'año' => 'integer',
        'orden' => 'integer',
        'opcional' => 'boolean',
        'secciones' => 'array',
        'enlaces' => 'array',
        'publicado' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function años(): array
    {
        return [
            1 => '1° Año',
            2 => '2° Año',
            3 => '3° Año',
            4 => '4° Año',
            5 => '5° Año',
            6 => '6° Año',
            7 => '7° Año',
        ];
    }

    /**
     * @return array<int, array{titulo: string, contenido: string}>
     */
    public function seccionesProfundizacion(): array
    {
        $secciones = $this->secciones;
        if (is_array($secciones) && $secciones !== []) {
            return array_values(array_filter($secciones, fn ($s) => ! empty($s['titulo'] ?? $s['contenido'] ?? null)));
        }

        return [
            ['titulo' => 'Contexto del toque', 'contenido' => ''],
            ['titulo' => 'Desarrollo en clase', 'contenido' => ''],
            ['titulo' => 'Referencias y escucha', 'contenido' => ''],
        ];
    }

    public function tieneProfundizacion(): bool
    {
        if (filled($this->resumen) || filled($this->contenido)) {
            return true;
        }
        foreach ($this->seccionesProfundizacion() as $s) {
            if (filled($s['contenido'] ?? null)) {
                return true;
            }
        }
        if (is_array($this->enlaces) && count($this->enlaces) > 0) {
            return true;
        }

        return false;
    }
}

