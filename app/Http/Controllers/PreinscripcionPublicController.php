<?php

namespace App\Http\Controllers;

use App\Domain\Personas\PreinscripcionService;
use App\Models\Bloque;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Preinscripción pública (sin cuenta): queda en lista de espera hasta que administración la resuelva. */
class PreinscripcionPublicController extends Controller
{
    public function create(): View
    {
        $sedes = Sede::query()->where('activo', true)
            ->with(['bloques' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')->get(['id', 'nombre']);

        return view('inscripcion.create', ['sedes' => $sedes]);
    }

    public function store(Request $request, PreinscripcionService $servicio): RedirectResponse
    {
        // Trampa para robots: no se guarda nada y la respuesta parece la misma.
        if (filled($request->input('sitio_web'))) {
            return redirect()->route('inscripcion.create')->with('success', self::EXITO);
        }

        $datos = $request->validate([
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'integer', 'exists:bloques,id'],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'dni' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tutor_nombre' => ['nullable', 'string', 'max:120'],
            'tutor_dni' => ['nullable', 'string', 'max:20'],
            'tutor_telefono' => ['nullable', 'string', 'max:30'],
            'tutor_vinculo' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);
        $datos['dni'] = filled($datos['dni'] ?? null) ? preg_replace('/\D+/', '', (string) $datos['dni']) : null;

        // Menores: el tutor tiene que estar anotado.
        if (\Illuminate\Support\Carbon::parse($datos['fecha_nacimiento'])->diffInYears(now()) < 18) {
            $request->validate([
                'tutor_nombre' => ['required', 'string', 'max:120'],
                'tutor_telefono' => ['required', 'string', 'max:30'],
                'tutor_vinculo' => ['required', 'string', 'max:40'],
            ], ['tutor_nombre.required' => 'Como es menor, anotá el nombre del tutor.', 'tutor_telefono.required' => 'Anotá un teléfono del tutor.', 'tutor_vinculo.required' => 'Indicá el vínculo con el tutor (madre, padre, tutor…).']);
        }

        if (filled($datos['bloque_id'] ?? null) && ! Bloque::query()->where('id', $datos['bloque_id'])->where('sede_id', $datos['sede_id'])->exists()) {
            return back()->withInput()->withErrors(['bloque_id' => 'Ese bloque no es de la sede elegida.']);
        }

        $servicio->crear($datos);

        return redirect()->route('inscripcion.create')->with('success', self::EXITO);
    }

    public const EXITO = 'Recibimos tu preinscripción. Quedaste en lista de espera: la administración de la sede te va a contactar cuando haya lugar o te confirme la inscripción.';
}
