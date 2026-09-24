<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identidad única de una persona. Sus funciones (alumno, profesor, coordinador,
 * contador…) se expresan con perfiles y asignaciones, nunca duplicando la persona.
 */
class Persona extends Model
{
    use Auditable, SoftDeletes;

    public const ESTADOS = [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
        'baja' => 'Baja',
    ];

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'fecha_nacimiento',
        'telefono',
        'email',
        'direccion',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'foto_path',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** Perfiles de alumno (normalmente uno; pueden quedar varios legacy hasta fusionarlos). */
    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    public function profesor(): HasOne
    {
        return $this->hasOne(Profesor::class);
    }

    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    public function becas(): HasManyThrough
    {
        return $this->hasManyThrough(Beca::class, Alumno::class);
    }

    public function fusionadaEn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fusionada_en_id');
    }

    /** El DNI se guarda normalizado (sin puntos ni espacios) para detectar duplicados. */
    public function setDniAttribute(?string $valor): void
    {
        $this->attributes['dni'] = self::normalizarDni($valor);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre.' '.($this->apellido ?? ''));
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento ? Carbon::parse($this->fecha_nacimiento)->age : null;
    }

    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($texto) {
            $like = '%'.$texto.'%';
            $q->where('nombre', 'like', $like)
                ->orWhere('apellido', 'like', $like)
                ->orWhere('dni', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('telefono', 'like', $like);
        });
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activo')->whereNull('fusionada_en_id');
    }

    /** DNI normalizado (solo dígitos y letras) para comparar duplicados. */
    public static function normalizarDni(?string $dni): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $dni) ?? '');

        return $limpio !== '' ? $limpio : null;
    }
}
