<?php

namespace App\Http\Requests;

use App\Models\Beca;
use Illuminate\Foundation\Http\FormRequest;

/** Cambio de estado / vigencia de una beca (web y API). */
class BecaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $beca = $this->route('beca');

        return $beca instanceof Beca && $beca->alumno && $this->user()->can('gestionarBecas', $beca->alumno);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Beca $beca */
        $beca = $this->route('beca');

        return [
            'estado' => 'required|in:'.implode(',', array_keys(Beca::ESTADOS)),
            'fecha_fin' => 'nullable|date|after_or_equal:'.$beca->fecha_inicio->toDateString(),
            'observaciones' => 'nullable|string|max:2000',
        ];
    }
}
