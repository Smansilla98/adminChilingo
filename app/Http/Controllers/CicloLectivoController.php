<?php

namespace App\Http\Controllers;

use App\Domain\Academico\CalendarioAcademico;
use App\Domain\Academico\CuotasDelCicloService;
use App\Domain\Personas\CicloLectivoService;
use App\Models\Alumno;
use App\Models\CicloLectivo;
use App\Models\Reinscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ciclo lectivo y reinscripción (web): abrir el ciclo, ver quién se reinscribió y dar de baja. */
class CicloLectivoController extends Controller
{
    public function index(): View
    {
        return view('ciclos.index', [
            'ciclos' => CicloLectivo::query()->orderByDesc('anio')->get(),
            'anioSugerido' => (int) now()->year + 1,
        ]);
    }

    public function store(Request $request, CicloLectivoService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100', 'unique:ciclos_lectivos,anio'],
            'nombre' => ['nullable', 'string', 'max:80'],
        ]);
        $ciclo = $servicio->abrir($datos, $request->user());
        $avisos = $servicio->avisar($ciclo);

        return redirect()->route('ciclos.show', $ciclo)
            ->with('success', 'Ciclo '.$ciclo->anio.' abierto. Se avisó a '.$avisos.' alumnos con cuenta.');
    }

    public function show(Request $request, CicloLectivo $ciclo, CicloLectivoService $servicio): View
    {
        $estado = in_array($request->query('estado'), [Reinscripcion::PENDIENTE, Reinscripcion::CONFIRMADA, Reinscripcion::BAJA], true)
            ? $request->query('estado') : null;

        $reinscripciones = Reinscripcion::query()->with('alumno')
            ->where('ciclo_lectivo_id', $ciclo->id)
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('estado')->get()
            ->sortBy(fn (Reinscripcion $r) => mb_strtolower((string) $r->alumno?->nombre_apellido));

        return view('ciclos.show', [
            'ciclo' => $ciclo,
            'reinscripciones' => $reinscripciones,
            'resumen' => $servicio->resumen($ciclo),
            'estado' => $estado,
            'calendario' => CalendarioAcademico::para($ciclo),
            'modulos' => $ciclo->modulos()->orderBy('id')->get(),
        ]);
    }

    /** Genera las cuotas mensuales marzo–febrero de cada bloque activo (no toca las que ya existen). */
    public function generarCuotas(Request $request, CicloLectivo $ciclo, CuotasDelCicloService $cuotas): RedirectResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0'],
            'montos' => ['nullable', 'array'],
            'montos.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $r = $cuotas->generar($ciclo, (float) $datos['monto'], array_filter((array) ($datos['montos'] ?? []), fn ($v) => $v !== null && $v !== ''), $request->user());

        return back()->with('success', 'Cuotas del ciclo: se crearon '.$r['creadas'].' y ya había '.$r['existentes'].' (no se tocaron).');
    }

    public function baja(Reinscripcion $reinscripcion, Request $request, CicloLectivoService $servicio): RedirectResponse
    {
        $servicio->darDeBaja($reinscripcion, $request->user());

        return back()->with('success', 'Alumno dado de baja del ciclo. Sus cuotas y asistencias quedan como están.');
    }

    /** El alumno ve el ciclo vigente y si ya se reinscribió. */
    public function miReinscripcion(Request $request): View
    {
        $ciclo = CicloLectivo::vigente();
        $alumnos = Alumno::query()->where('user_id', $request->user()->id)->where('activo', true)->get();
        $estados = $ciclo ? Reinscripcion::query()->where('ciclo_lectivo_id', $ciclo->id)
            ->whereIn('alumno_id', $alumnos->pluck('id'))->pluck('estado', 'alumno_id') : collect();

        return view('ciclos.mi-reinscripcion', ['ciclo' => $ciclo, 'alumnos' => $alumnos, 'estados' => $estados]);
    }

    public function confirmar(Request $request, CicloLectivoService $servicio): RedirectResponse
    {
        $n = $servicio->confirmar($request->user());

        return redirect()->route('mi-reinscripcion.show')
            ->with('success', $n > 0 ? '¡Listo! Quedaste reinscripto.' : 'Ya estabas reinscripto.');
    }
}
