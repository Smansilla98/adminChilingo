<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Rol o permiso asignado explícitamente a una persona, con alcance.
 */
class Asignacion extends Model
{
    use Auditable;

    public const AMBITO_GLOBAL = 'global';

    public const AMBITO_SEDE = 'sede';

    public const AMBITO_BLOQUE = 'bloque';

    public const AMBITOS = [
        self::AMBITO_GLOBAL => 'Global',
        self::AMBITO_SEDE => 'Sede',
        self::AMBITO_BLOQUE => 'Bloque',
    ];

    protected $table = 'asignaciones';

    protected $fillable = [
        'persona_id', 'role_id', 'permission_id', 'ambito_tipo', 'sede_id', 'bloque_id',
        'desde', 'hasta', 'activo', 'notas', 'creado_por',
    ];

    protected $casts = [
        'desde' => 'date',
        'hasta' => 'date',
        'activo' => 'boolean',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Activas y dentro del período de vigencia. */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->where('activo', true)
            ->where(fn (Builder $q) => $q->whereNull('desde')->orWhere('desde', '<=', $hoy))
            ->where(fn (Builder $q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy));
    }

    public function etiquetaAmbito(): string
    {
        return match ($this->ambito_tipo) {
            self::AMBITO_SEDE => $this->sede?->nombre ?? 'Sede #'.$this->sede_id,
            self::AMBITO_BLOQUE => $this->bloque?->nombre ?? 'Bloque #'.$this->bloque_id,
            default => 'Global',
        };
    }
}
