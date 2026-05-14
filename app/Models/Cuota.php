<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Cuota extends Model
{
    public const ALCANCE_BLOQUE = 'bloque';

    public const ALCANCE_SEDE = 'sede';

    public const ALCANCE_GENERAL = 'general';

    protected $table = 'cuotas';

    protected $fillable = [
        'bloque_id',
        'sede_id',
        'alcance',
        'nombre',
        'año',
        'mes',
        'fecha_vencimiento',
        'monto',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'año' => 'integer',
        'mes' => 'integer',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Cuota efectiva para un bloque y período: primero por bloque, luego diferencial por sede, luego general escuela.
     */
    public static function resolveForBloque(int $bloqueId, int $año, int $mes): ?self
    {
        if (! Schema::hasColumn('cuotas', 'alcance')) {
            return static::query()
                ->where('bloque_id', $bloqueId)
                ->where('año', $año)
                ->where('mes', $mes)
                ->orderByDesc('activo')
                ->orderByDesc('id')
                ->first();
        }

        $bloque = Bloque::query()->find($bloqueId);

        $porBloque = static::query()
            ->where('alcance', self::ALCANCE_BLOQUE)
            ->where('bloque_id', $bloqueId)
            ->where('año', $año)
            ->where('mes', $mes)
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->first();
        if ($porBloque) {
            return $porBloque;
        }

        if ($bloque && $bloque->sede_id) {
            $porSede = static::query()
                ->where('alcance', self::ALCANCE_SEDE)
                ->where('sede_id', $bloque->sede_id)
                ->where('año', $año)
                ->where('mes', $mes)
                ->orderByDesc('activo')
                ->orderByDesc('id')
                ->first();
            if ($porSede) {
                return $porSede;
            }
        }

        return static::query()
            ->where('alcance', self::ALCANCE_GENERAL)
            ->whereNull('bloque_id')
            ->whereNull('sede_id')
            ->where('año', $año)
            ->where('mes', $mes)
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->first();
    }

    public function alcanceNormalizado(): string
    {
        if (! Schema::hasColumn($this->getTable(), 'alcance')) {
            return self::ALCANCE_BLOQUE;
        }
        $a = $this->attributes['alcance'] ?? self::ALCANCE_BLOQUE;

        return in_array($a, [self::ALCANCE_BLOQUE, self::ALCANCE_SEDE, self::ALCANCE_GENERAL], true)
            ? $a
            : self::ALCANCE_BLOQUE;
    }

    /**
     * Alumnos asignados a esta cuota (opcional). Si está vacío, aplica según alcance (bloque / sede / general).
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'cuota_alumno')->withTimestamps();
    }

    public function pagoDetalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class);
    }

    public function getNombreMesAttribute(): ?string
    {
        if (! $this->mes) {
            return null;
        }
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $meses[$this->mes] ?? (string) $this->mes;
    }

    /**
     * Si la cuota tiene lista en cuota_alumno, solo esos; si no, según alcance (todos los del bloque / de la sede / de la escuela).
     */
    public function aplicaAAlumno(Alumno $alumno): bool
    {
        $alcance = $this->alcanceNormalizado();

        if ($alcance === self::ALCANCE_BLOQUE) {
            if (! $this->bloque_id) {
                return false;
            }
            $enBloque = $alumno->bloques()->where('bloques.id', $this->bloque_id)->exists()
                || (int) $alumno->bloque_id === (int) $this->bloque_id;
            if (! $enBloque) {
                return false;
            }
        } elseif ($alcance === self::ALCANCE_SEDE) {
            if (! $this->sede_id) {
                return false;
            }
            $sid = (int) $this->sede_id;
            $enSedePorBloque = $alumno->bloques()->where('bloques.sede_id', $sid)->exists();
            $enSedePrincipal = (int) ($alumno->sede_id ?? 0) === $sid;
            if (! $enSedePorBloque && ! $enSedePrincipal) {
                return false;
            }
        } else {
            // general: al menos un vínculo con la escuela (bloque)
            $tieneBloque = $alumno->bloques()->exists() || $alumno->bloque_id;
            if (! $tieneBloque) {
                return false;
            }
        }

        if ($this->alumnos()->count() === 0) {
            return true;
        }

        return $this->alumnos()->where('alumnos.id', $alumno->id)->exists();
    }
}
