<?php

namespace App\Domain\Agenda;

use App\Models\Sede;
use Illuminate\Support\Facades\Schema;

/**
 * Alta y edición de sedes (web y API): reglas, valores por defecto de la
 * liquidación docente y columnas presentes en la base.
 */
class SedeService
{
    public const TIPOS_PROPIEDAD = [
        'propia' => 'Propia',
        'alquilada' => 'Alquilada',
        'compartida' => 'Compartida',
        'otro' => 'Otro',
    ];

    /** @return array<string, mixed> */
    public function reglas(?Sede $sede = null): array
    {
        return [
            'nombre' => 'required|string|max:255|unique:sedes,nombre'.($sede ? ','.$sede->id : ''),
            'direccion' => 'nullable|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90|required_with:longitud',
            'longitud' => 'nullable|numeric|between:-180,180|required_with:latitud',
            'en_mapa' => 'boolean',
            'tipo_propiedad' => 'nullable|string|in:'.implode(',', array_keys(self::TIPOS_PROPIEDAD)),
            'costo_alquiler_mensual' => 'nullable|numeric|min:0',
            'liquidacion_retencion_escuela' => 'nullable|numeric|min:0',
            'liquidacion_porc_docente' => 'nullable|numeric|min:0|max:100',
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados (con `activo` ya resuelto)
     */
    public function crear(array $datos): Sede
    {
        return Sede::create($this->normalizar($datos));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Sede $sede, array $datos): Sede
    {
        $sede->update($this->normalizar($datos));

        return $sede;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        if (empty($datos['tipo_propiedad'])) {
            $datos['tipo_propiedad'] = 'alquilada';
        }
        if (($datos['liquidacion_retencion_escuela'] ?? null) === null || $datos['liquidacion_retencion_escuela'] === '') {
            $datos['liquidacion_retencion_escuela'] = 0;
        }
        if (($datos['liquidacion_porc_docente'] ?? null) === null || $datos['liquidacion_porc_docente'] === '') {
            $datos['liquidacion_porc_docente'] = 40;
        }

        // Solo columnas presentes en la tabla (algunas llegaron en migraciones posteriores).
        $permitidas = ['nombre', 'direccion', 'activo'];
        foreach (['tipo_propiedad', 'costo_alquiler_mensual', 'liquidacion_retencion_escuela', 'liquidacion_porc_docente', 'latitud', 'longitud', 'en_mapa'] as $col) {
            if (Schema::hasColumn('sedes', $col)) {
                $permitidas[] = $col;
            }
        }

        return array_intersect_key($datos, array_flip($permitidas));
    }
}
