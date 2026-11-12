<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Personas\LegajoMenorService;
use App\Http\Controllers\Controller;
use App\Models\LegajoMenor;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Legajo de menor (API y app). La carga del apto va por POST multipart. */
class LegajoMenorController extends Controller
{
    public function show(Persona $persona, LegajoMenorService $legajos): JsonResponse
    {
        $this->authorize('view', $persona);

        return response()->json(['data' => $this->presentar($persona, $legajos)]);
    }

    public function update(Request $request, Persona $persona, LegajoMenorService $legajos): JsonResponse
    {
        $this->authorize('update', $persona);
        $datos = $request->validate($legajos->reglas($persona));
        $legajos->guardar($persona, $datos, $request->file('apto_medico'), $request->user());

        return response()->json(['data' => $this->presentar($persona, $legajos)]);
    }

    public function apto(Persona $persona): Response
    {
        $this->authorize('view', $persona);
        $legajo = LegajoMenor::query()->where('persona_id', $persona->id)->firstOrFail();
        abort_if(! $legajo->apto_medico_path, 404);

        return LegajoMenorService::respuestaApto($legajo);
    }

    /** @return array<string, mixed> */
    private function presentar(Persona $persona, LegajoMenorService $legajos): array
    {
        $legajo = LegajoMenor::query()->firstOrNew(['persona_id' => $persona->id]);

        return [
            'persona_id' => $persona->id,
            'es_menor' => $legajos->esMenor($persona),
            'tutor' => $legajo->tutor_nombre ? [
                'nombre' => $legajo->tutor_nombre,
                'dni' => $legajo->tutor_dni,
                'telefono' => $legajo->tutor_telefono,
                'vinculo' => $legajo->tutor_vinculo,
            ] : null,
            'uso_imagen' => $legajo->uso_imagen,
            'uso_imagen_fecha' => $legajo->uso_imagen_fecha?->toDateString(),
            'apto_medico' => [
                'cargado' => (bool) $legajo->apto_medico_path,
                'nombre' => $legajo->apto_medico_nombre,
                'vence' => $legajo->apto_medico_vence?->toDateString(),
                'dias_para_vencer' => $legajo->diasParaVencerApto(),
                'vencido' => $legajo->diasParaVencerApto() !== null && $legajo->diasParaVencerApto() < 0,
            ],
        ];
    }
}
