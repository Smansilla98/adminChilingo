<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionPedagogica extends Model
{
    public const TIPOS = [
        'clase' => 'Hoy en clase',
        'avance' => 'Avance',
        'dificultad' => 'Dificultad',
        'repertorio' => 'Repertorio',
        'practica' => 'Práctica en casa',
        'nota' => 'Nota',
    ];

    public const EJES = [
        'tecnica' => 'Técnica',
        'ritmo' => 'Ritmo',
        'instrumento' => 'Instrumento',
        'musicalidad' => 'Musicalidad',
        'repertorio' => 'Repertorio',
        'practica' => 'Práctica',
    ];

    protected $table = 'observaciones_pedagogicas';

    protected $fillable = [
        'alumno_id',
        'user_id',
        'bloque_id',
        'fecha',
        'tipo',
        'toque',
        'cuerpo',
        'eje',
        'proximo_paso',
        'visible_alumno',
    ];

    protected $casts = [
        'fecha' => 'date',
        'visible_alumno' => 'boolean',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function etiquetaEje(): string
    {
        return self::EJES[$this->eje] ?? ($this->eje ?: '');
    }
}
