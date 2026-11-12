<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\ReglasCobro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Configuración → Reglas de cobro: recargo por mora, descuento familiar y pago adelantado. */
class ReglasCobroController extends Controller
{
    public function edit(): View
    {
        return view('configuracion.reglas-cobro', [
            'reglas' => ReglasCobro::actuales(),
            'tipos' => ReglasCobro::TIPOS_RECARGO,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate(ReglasCobro::reglasValidacion());
        ReglasCobro::guardar($datos, $request->user()->id);

        return back()->with('success', 'Reglas de cobro guardadas. Se aplican a los estados de cuenta desde ahora.');
    }
}
