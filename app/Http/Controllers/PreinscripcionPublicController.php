<?php

namespace App\Http\Controllers;

use App\Domain\Personas\PreinscripcionPasosService;
use App\Models\Preinscripcion;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Preinscripción pública por pasos (sin cuenta). Cada paso es un envío; el link con el token
 * (?p=…) deja seguir donde se quedó. Queda en lista de espera hasta que administración la resuelva.
 */
class PreinscripcionPublicController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $sedes = Sede::query()->where('activo', true)
            ->with(['bloques' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')->get(['id', 'nombre']);

        $token = (string) $request->query('p', '');
        if ($token === '') {
            return view('inscripcion.create', ['sedes' => $sedes, 'pre' => null, 'paso' => 1]);
        }

        $pre = Preinscripcion::query()->where('token', $token)->first();
        if (! $pre) {
            return redirect()->route('inscripcion.create')->withErrors(['token' => 'No encontramos esa preinscripción. Empezá de nuevo.']);
        }
        if ($pre->estado !== Preinscripcion::EN_CURSO) {
            return view('inscripcion.gracias', ['pre' => $pre]);
        }

        return view('inscripcion.create', ['sedes' => $sedes, 'pre' => $pre, 'paso' => $pre->paso + 1]);
    }

    public function paso(Request $request, int $paso, PreinscripcionPasosService $pasos): RedirectResponse
    {
        // Campo trampa: las personas no lo ven; los bots lo completan. No se guarda nada.
        if (filled($request->input('sitio_web'))) {
            return redirect()->route('inscripcion.create')->with('success', self::EXITO);
        }
        if ($paso < 1 || $paso > Preinscripcion::PASOS) {
            abort(404);
        }

        if ($paso === 1) {
            $datos = $request->validate([
                'sede_id' => ['required', 'integer', 'exists:sedes,id'],
                'bloque_id' => ['nullable', 'integer', 'exists:bloques,id'],
                'nombre' => ['required', 'string', 'max:120'],
                'apellido' => ['required', 'string', 'max:120'],
            ], [], ['nombre' => 'nombre', 'apellido' => 'apellido']);
            $pre = $pasos->iniciar($datos);

            return redirect()->route('inscripcion.create', ['p' => $pre->token]);
        }

        $pre = Preinscripcion::query()->where('token', (string) $request->input('p'))->first();
        if (! $pre || $pre->estado !== Preinscripcion::EN_CURSO) {
            return redirect()->route('inscripcion.create')->withErrors(['token' => 'No encontramos esa preinscripción. Empezá de nuevo.']);
        }
        if ($paso !== $pre->paso + 1) {
            // No se puede saltar ni repetir un paso ya completado: se vuelve al que corresponde.
            return redirect()->route('inscripcion.create', ['p' => $pre->token]);
        }

        $datos = $this->datosDelPaso($request, $paso, $pre);
        $pasos->completarPaso($pre, $paso, $datos);

        // Tras el último paso, el mismo link muestra la confirmación (lista de espera).
        return redirect()->route('inscripcion.create', ['p' => $pre->token]);
    }

    /** @return array<string, mixed> */
    private function datosDelPaso(Request $request, int $paso, Preinscripcion $pre): array
    {
        return match ($paso) {
            2 => $this->validarPaso2($request),
            3 => $this->validarPaso3($request, $pre),
            default => $request->validate(['notas' => ['nullable', 'string', 'max:1000']]),
        };
    }

    /** @return array<string, mixed> */
    private function validarPaso2(Request $request): array
    {
        $datos = $request->validate([
            'dni' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
        ], [], ['fecha_nacimiento' => 'fecha de nacimiento']);
        $datos['dni'] = filled($datos['dni'] ?? null) ? preg_replace('/\D+/', '', (string) $datos['dni']) : null;

        return $datos;
    }

    /** @return array<string, mixed> */
    private function validarPaso3(Request $request, Preinscripcion $pre): array
    {
        $menor = Carbon::parse($pre->fecha_nacimiento)->diffInYears(now()) < 18;
        $datos = $request->validate([
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tutor_nombre' => [$menor ? 'required' : 'nullable', 'string', 'max:120'],
            'tutor_vinculo' => [$menor ? 'required' : 'nullable', 'string', 'max:40'],
            'tutor_telefono' => [$menor ? 'required' : 'nullable', 'string', 'max:30'],
            'tutor_dni' => ['nullable', 'string', 'max:20'],
        ], [
            'tutor_nombre.required' => 'Como sos menor, anotá el nombre del tutor.',
            'tutor_vinculo.required' => 'Indicá el vínculo con el tutor (madre, padre, tutor…).',
            'tutor_telefono.required' => 'Anotá un teléfono del tutor.',
        ]);
        $datos['tutor_dni'] = filled($datos['tutor_dni'] ?? null) ? preg_replace('/\D+/', '', (string) $datos['tutor_dni']) : null;

        return $datos;
    }

    public const EXITO = 'Recibimos tu preinscripción. Quedaste en lista de espera: la administración de la sede te va a contactar cuando haya lugar o te confirme la inscripción.';
}
