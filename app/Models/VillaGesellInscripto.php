<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillaGesellInscripto extends Model
{
    protected $table = 'villa_gesell_inscriptos';

    public const ESTADOS_PAGO = [
        'pendiente' => 'Pendiente',
        'sena' => 'Seña',
        'pago' => 'Pago',
        'beca' => 'Beca / no paga',
    ];

    public const TALLES = [
        'XS' => 'XS',
        'S' => 'S',
        'M' => 'M',
        'L' => 'L',
        'XL' => 'XL',
        'XXL' => 'XXL',
        'XXXL' => 'XXXL',
    ];

    public const TAMBORES = [
        'Redoblante',
        'Repique',
        'Medio',
        'Fondo Agudo',
        'Fondo Grave',
        'Timbal',
        'Platillo',
        'Agogó',
        'Otro',
    ];

    public const ORIGENES_TAMBOR = [
        'propio' => 'Propio',
        'sede' => 'De la sede',
        'gira' => 'De la gira',
    ];

    protected $fillable = [
        'alumno_id',
        'estado_pago',
        'monto_esperado',
        'monto_pagado',
        'plaza',
        'lista_espera',
        'fecha_desde',
        'fecha_hasta',
        'talle_remera',
        'tambor_principal',
        'tambor_secundario',
        'tambor_terciario',
        'tambor_principal_origen',
        'tambor_secundario_origen',
        'tambor_terciario_origen',
        'notas',
    ];

    protected $casts = [
        'monto_esperado' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'plaza' => 'integer',
        'lista_espera' => 'boolean',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function etiquetaPago(): string
    {
        return self::ESTADOS_PAGO[$this->estado_pago] ?? $this->estado_pago;
    }

    public function diasUtilizados(): int
    {
        if (! $this->fecha_desde || ! $this->fecha_hasta) {
            return 0;
        }

        return (int) $this->fecha_desde->diffInDays($this->fecha_hasta) + 1;
    }

    public function saldo(): float
    {
        return max(0, (float) $this->monto_esperado - (float) $this->monto_pagado);
    }
}
