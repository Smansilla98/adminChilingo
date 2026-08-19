<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioItem extends Model
{
    protected $table = 'inventario_items';

    public const TIPOS = [
        'instrumento' => 'Instrumentos',
        'herramienta' => 'Herramientas',
        'accesorio' => 'Accesorios',
        'repuesto' => 'Repuestos',
        'parche' => 'Parches de repuesto',
        'tela' => 'Telas',
        'masa' => 'Masas',
        'otro' => 'Otros',
    ];

    public const PROPIETARIOS = [
        'escuela' => 'Escuela',
        'alumno' => 'Alumno',
    ];

    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'bueno' => 'Bueno',
        'regular' => 'Regular',
        'reparacion' => 'En reparación',
        'baja' => 'Baja',
    ];

    public const ORIGENES = [
        'comprado' => 'Comprado',
        'donado' => 'Donado',
        'prestado' => 'Prestado',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'sede_id',
        'tipo',
        'nombre',
        'codigo',
        'es_consumible',
        'cantidad',
        'unidad',
        'propietario_tipo',
        'alumno_id',
        'marca',
        'modelo',
        'linea',
        'material',
        'color',
        'medida',
        'diametro_pulgadas',
        'torres',
        'anio_fabricacion',
        'origen_adquisicion',
        'fecha_adquisicion',
        'precio',
        'estado',
        'reparado_en',
        'detalle_reparacion',
        'utilitario',
        'notas',
    ];

    protected $casts = [
        'es_consumible' => 'boolean',
        'utilitario' => 'boolean',
        'cantidad' => 'decimal:2',
        'precio' => 'decimal:2',
        'diametro_pulgadas' => 'decimal:2',
        'torres' => 'integer',
        'anio_fabricacion' => 'integer',
        'fecha_adquisicion' => 'date',
        'reparado_en' => 'date',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class)->orderByDesc('id');
    }

    public function asegurarCodigo(): void
    {
        if (filled($this->codigo)) {
            return;
        }
        $this->codigo = sprintf('CHL-%04d', (int) $this->id);
        $this->save();
    }

    /**
     * Ficha para QR / URL pública: sin alumno, DNI ni notas internas.
     *
     * @return array{codigo: string, nombre: string, tipo: string, sede: ?string, estado: string, marca: ?string, medida: ?string}
     */
    public function fichaPublica(): array
    {
        if ($this->exists) {
            $this->loadMissing('sede');
        }

        return [
            'codigo' => (string) $this->codigo,
            'nombre' => (string) $this->nombre,
            'tipo' => $this->tipo_label,
            'sede' => $this->sede?->nombre,
            'estado' => self::ESTADOS[$this->estado] ?? (string) $this->estado,
            'marca' => $this->marca,
            'medida' => $this->medida,
        ];
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getPropietarioLabelAttribute(): string
    {
        return self::PROPIETARIOS[$this->propietario_tipo] ?? $this->propietario_tipo;
    }
}
