<?php

namespace App\Http\Requests;

use App\Models\Persona;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de personas. Compartido por el panel web y la API v1 para que
 * ambos clientes apliquen exactamente las mismas reglas.
 */
class PersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $persona = $this->route('persona');

        return $persona instanceof Persona
            ? $this->user()->can('update', $persona)
            : $this->user()->can('create', Persona::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $persona = $this->route('persona');

        return [
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'dni' => 'nullable|string|max:20|unique:personas,dni'.($persona instanceof Persona ? ','.$persona->id : ''),
            'fecha_nacimiento' => 'nullable|date|before:today',
            'telefono' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'contacto_emergencia_nombre' => 'nullable|string|max:255',
            'contacto_emergencia_telefono' => 'nullable|string|max:40',
            'estado' => 'required|in:'.implode(',', array_keys(Persona::ESTADOS)),
            'observaciones' => 'nullable|string|max:2000',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['dni.unique' => 'Ya hay otra persona con ese DNI. Si es la misma, fusionalas desde su ficha.'];
    }
}
