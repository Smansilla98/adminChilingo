<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\AjustesCuentas;
use App\Domain\Personas\CodigoAltaService;
use App\Models\Auditoria;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Cuentas de alumnos: código de alta por persona y ajustes del registro. */
class CuentasAlumnosController extends Controller
{
    /** Genera el código de alta: se muestra una sola vez en la ficha. */
    public function codigoAlta(Request $request, Persona $persona, CodigoAltaService $codigos): RedirectResponse
    {
        // Autoriza el servicio: la persona tiene que ser alumno visible para quien pide.
        return back()->with('codigo_alta', $codigos->generar($persona, $request->user()));
    }

    public function ajustes(): View
    {
        return view('configuracion.cuentas', ['ajustes' => AjustesCuentas::todos()]);
    }

    public function guardarAjustes(Request $request): RedirectResponse
    {
        $antes = AjustesCuentas::todos();
        $valores = ['registro_requiere_codigo' => $request->boolean('registro_requiere_codigo')];
        AjustesCuentas::guardar($valores, $request->user()->id);
        Auditoria::registrar('ajustes.cuentas', 'ajustes', $antes, $valores);

        return back()->with('success', 'Ajustes de cuentas guardados.');
    }
}
