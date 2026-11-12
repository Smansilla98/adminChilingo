<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préstamo de un instrumento. Estado: devuelto (tiene fecha de devolución), vencido (pasó la
 * fecha prevista sin volver) o prestado.
 */
class InventarioPrestamo extends Model
{
    protected $table = 'inventario_prestamos';

    public const ESTADOS = [
        'prestado' => 'Prestado',
        'vencido' => 'Vencido',
        'devuelto' => 'Devuelto',
    ];

    protected $fillable = [
        'inventario_item_id', 'alumno_id', 'persona_id', 'responsable_nombre',
        'entregado_el', 'devolucion_prevista', 'devuelto_el',
        'deposito', 'deposito_devuelto', 'condicion_entrega', 'condicion_devolucion', 'notas',
        'entregado_por', 'recibido_por',
    ];

    protected $casts = [
        'entregado_el' => 'date',
        'devolucion_prevista' => 'date',
        'devuelto_el' => 'date',
        'deposito' => 'decimal:2',
        'deposito_devuelto' => 'boolean',
    ];

    /** @return BelongsTo<InventarioItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventarioItem::class, 'inventario_item_id');
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<User, $this> */
    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    /** @param  Builder<InventarioPrestamo>  $query */
    public function scopeActivos(Builder $query): void
    {
        $query->whereNull('devuelto_el');
    }

    /** @param  Builder<InventarioPrestamo>  $query */
    public function scopeVencidos(Builder $query): void
    {
        $query->whereNull('devuelto_el')->whereDate('devolucion_prevista', '<', now()->toDateString());
    }

    public function estado(): string
    {
        if ($this->devuelto_el) {
            return 'devuelto';
        }

        return $this->devolucion_prevista->lt(today()) ? 'vencido' : 'prestado';
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado()];
    }

    public function responsable(): string
    {
        if ($this->alumno) {
            return (string) $this->alumno->nombre_apellido;
        }
        if ($this->persona) {
            return $this->persona->nombre_completo;
        }

        return (string) ($this->responsable_nombre ?: '—');
    }
}
