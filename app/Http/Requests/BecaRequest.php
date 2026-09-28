<?php

namespace App\Http\Requests;

use App\Models\Beca;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Otorgar una beca (web y API). La autorización sobre el alumno concreto
 * (`gestionarBecas`) la hace el controlador una vez resuelto el alumno.
 */
class BecaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alumno_id' => 'required|integer',
            'tipo' => 'required|in:'.implode(',', array_keys(Beca::TIPOS)),
            'porcentaje' => 'nullable|required_if:tipo,porcentaje|numeric|min:1|max:100',
            'monto' => 'nullable|required_if:tipo,monto_fijo|numeric|min:1',
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'motivo' => 'nullable|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'observaciones' => 'nullable|string|max:2000',
        ];
    }
}
