<?php

namespace App\Models;

use App\Support\VillaGesellCalculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VillaGesellConfig extends Model
{
    protected $table = 'villa_gesell_config';

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'cupo_maximo',
        'aporte_esperado',
        'notas',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'cupo_maximo' => 'integer',
        'aporte_esperado' => 'decimal:2',
    ];

    public function cantidadDias(): int
    {
        return VillaGesellCalculo::cantidadDias($this->fecha_inicio, $this->fecha_fin);
    }

    /**
     * @return list<Carbon>
     */
    public function rangoFechas(): array
    {
        return VillaGesellCalculo::rangoFechas($this->fecha_inicio, $this->fecha_fin);
    }

    public function valorPorDia(): float
    {
        return (float) $this->aporte_esperado;
    }

    public function aporteSiCubreTodosLosDias(int $personas = 1): float
    {
        return VillaGesellCalculo::porDiaPorDias($this->valorPorDia(), $this->cantidadDias()) * max(0, $personas);
    }
}
