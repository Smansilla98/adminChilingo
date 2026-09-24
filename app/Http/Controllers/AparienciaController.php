<?php

namespace App\Http\Controllers;

use App\Support\AparienciaTema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AparienciaController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $tema = AparienciaTema::normalizar(
            Schema::hasColumn('users', 'apariencia_json') ? ($user->apariencia_json ?? null) : null
        );

        return view('apariencia.edit', [
            'tema' => $tema,
            'acentos' => AparienciaTema::ACENTOS,
            'fuentesTitulo' => AparienciaTema::FUENTES_TITULO,
            'fuentesCuerpo' => AparienciaTema::FUENTES_CUERPO,
            'temas' => AparienciaTema::TEMAS,
            'defaults' => AparienciaTema::DEFAULTS,
            'esDefault' => AparienciaTema::esDefault($tema),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        if (! Schema::hasColumn('users', 'apariencia_json')) {
            return back()->with('error', 'Falta migrar la columna de apariencia.');
        }

        $validated = $request->validate([
            'accent' => [
                'required',
                'string',
                'max:7',
                function (string $attr, mixed $value, \Closure $fail) {
                    $hex = AparienciaTema::sanitizarHex((string) $value);
                    if ($hex === null) {
                        $fail('El color de acento no es un hex válido.');
                    }
                },
            ],
            'font_display' => ['required', 'string', Rule::in(array_keys(AparienciaTema::FUENTES_TITULO))],
            'font_body' => ['required', 'string', Rule::in(array_keys(AparienciaTema::FUENTES_CUERPO))],
            'tema' => ['nullable', 'string', Rule::in(array_keys(AparienciaTema::TEMAS))],
        ]);
        $tema = AparienciaTema::normalizar([
            'accent' => $validated['accent'],
            'font_display' => $validated['font_display'],
            'font_body' => $validated['font_body'],
            'tema' => $validated['tema'] ?? ($user->apariencia_json['tema'] ?? null),
        ]);

        $user->apariencia_json = $tema;
        $user->save();

        return redirect()
            ->route('apariencia.edit')
            ->with('success', 'Apariencia guardada. Se aplica en todo el sistema.');
    }

    /** Cambio rápido de tema desde el menú de usuario (claro / oscuro / sistema). */
    public function tema(Request $request)
    {
        $validated = $request->validate([
            'tema' => ['required', 'string', Rule::in(array_keys(AparienciaTema::TEMAS))],
        ]);
        $user = auth()->user();
        if (! Schema::hasColumn('users', 'apariencia_json')) {
            return back()->with('error', 'Falta migrar la columna de apariencia.');
        }

        $actual = is_array($user->apariencia_json) ? $user->apariencia_json : [];
        $user->apariencia_json = AparienciaTema::normalizar(array_merge($actual, ['tema' => $validated['tema']]));
        $user->save();

        return back();
    }

    public function reset()
    {
        $user = auth()->user();
        if (! Schema::hasColumn('users', 'apariencia_json')) {
            return back()->with('error', 'Falta migrar la columna de apariencia.');
        }

        $user->apariencia_json = null;
        $user->save();

        return redirect()
            ->route('apariencia.edit')
            ->with('success', 'Se restableció la apariencia por defecto.');
    }
}
