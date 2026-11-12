<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\AjustesCuentas;
use App\Domain\Personas\CodigoAltaService;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cuentas de alumnos (app): código de alta y ajustes del registro. */
class CuentasController extends Controller
{
    public function codigoAlta(Request $request, Persona $persona, CodigoAltaService $codigos): JsonResponse
    {
        // Autoriza el servicio: la persona tiene que ser alumno visible para quien pide.
        return response()->json($codigos->generar($persona, $request->user()), 201);
    }

    public function ajustes(): JsonResponse
    {
        return response()->json(['data' => AjustesCuentas::todos()]);
    }

    public function guardarAjustes(Request $request): JsonResponse
    {
        $request->validate(['registro_requiere_codigo' => ['required', 'boolean']]);
        $antes = AjustesCuentas::todos();
        $valores = ['registro_requiere_codigo' => $request->boolean('registro_requiere_codigo')];
        AjustesCuentas::guardar($valores, $request->user()->id);
        Auditoria::registrar('ajustes.cuentas', 'ajustes', $antes, $valores);

        return response()->json(['data' => AjustesCuentas::todos()]);
    }
}
