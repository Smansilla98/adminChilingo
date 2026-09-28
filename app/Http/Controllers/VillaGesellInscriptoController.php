<?php

namespace App\Http\Controllers;

use App\Domain\VillaGesell\VillaGesellAdmin;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\VillaGesellInscripto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VillaGesellInscriptoController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $config = $this->gira->config();
        $estado = (string) request()->query('estado', '');
        $inscriptos = VillaGesellInscripto::query()
            ->with('alumno.sede')
            ->when(
                $estado !== '' && array_key_exists($estado, VillaGesellInscripto::ESTADOS_PAGO),
                fn ($q) => $q->where('estado_pago', $estado)
            )
            ->orderByRaw('lista_espera asc')
            ->orderByRaw('plaza is null')
            ->orderBy('plaza')
            ->get();

        return view('villa-gesell.inscriptos.index', compact('inscriptos', 'config', 'estado'));
    }

    public function create(): View
    {
        $config = $this->gira->config();
        $alumnos = $this->alumnosDisponibles();
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre')->get();
        $bloques = Bloque::query()->with(['sede', 'profesor'])->where('activo', true)->orderBy('nombre')->get();
        $profesores = Profesor::query()->where('activo', true)->orderBy('nombre')->get();
        $inscripto = app(VillaGesellAdmin::class)->nuevaInscripcion();

        return view('villa-gesell.inscriptos.create', compact('alumnos', 'config', 'inscripto', 'sedes', 'bloques', 'profesores'));
    }

    public function storeAlumnoRapido(Request $request): JsonResponse
    {
        $request->merge([
            'dni' => filled($request->input('dni')) ? trim((string) $request->input('dni')) : null,
            'telefono' => filled($request->input('telefono')) ? trim((string) $request->input('telefono')) : null,
            'fecha_nacimiento' => filled($request->input('fecha_nacimiento')) ? $request->input('fecha_nacimiento') : null,
            'sede_id' => filled($request->input('sede_id')) ? $request->input('sede_id') : null,
            'bloque_id' => filled($request->input('bloque_id')) ? $request->input('bloque_id') : null,
            'profesor_id' => filled($request->input('profesor_id')) ? $request->input('profesor_id') : null,
            'instrumento_principal' => filled($request->input('instrumento_principal'))
                ? $request->input('instrumento_principal')
                : 'Otro',
        ]);

        $admin = app(VillaGesellAdmin::class);
        $data = $request->validate($admin->reglasAlumnoRapido(), [
            'nombre_apellido.required' => 'El nombre es obligatorio.',
            'dni.unique' => 'Ese DNI ya está cargado en el padrón.',
        ]);
        ['alumno' => $alumno, 'bloque' => $bloque, 'profesor' => $profesorNombre] = $admin->altaRapidaAlumno($data);

        return response()->json([
            'ok' => true,
            'alumno' => [
                'id' => $alumno->id,
                'nombre_apellido' => $alumno->nombre_apellido,
                'dni' => $alumno->dni,
                'bloque' => $bloque?->nombre,
                'profesor' => $profesorNombre,
            ],
            'message' => 'Alumno creado. Ya lo podés inscribir a la gira.',
        ]);
    }

    public function storeProfesorRapido(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ], [
            'nombre.required' => 'El nombre del profesor es obligatorio.',
        ]);

        $profesor = app(VillaGesellAdmin::class)->altaRapidaProfesor($data['nombre'], $data['telefono'] ?? null);

        return response()->json([
            'ok' => true,
            'profesor' => [
                'id' => $profesor->id,
                'nombre' => $profesor->nombre,
            ],
            'message' => 'Profesor creado. Después podés completar su ficha.',
        ]);
    }

    public function storeBloqueRapido(Request $request): JsonResponse
    {
        $request->merge([
            'sede_id' => filled($request->input('sede_id')) ? $request->input('sede_id') : null,
            'profesor_id' => filled($request->input('profesor_id')) ? $request->input('profesor_id') : null,
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'profesor_id' => ['nullable', 'exists:profesores,id'],
        ], [
            'nombre.required' => 'El nombre del bloque es obligatorio.',
        ]);

        $bloque = app(VillaGesellAdmin::class)->altaRapidaBloque($data['nombre'], isset($data['sede_id']) ? (int) $data['sede_id'] : null, isset($data['profesor_id']) ? (int) $data['profesor_id'] : null);

        return response()->json([
            'ok' => true,
            'bloque' => [
                'id' => $bloque->id,
                'nombre' => $bloque->nombre,
                'sede_id' => $bloque->sede_id,
                'profesor_id' => $bloque->profesor_id,
                'label' => trim($bloque->nombre
                    .($bloque->sede ? ' · '.$bloque->sede->nombre : '')
                    .($bloque->profesor ? ' · '.$bloque->profesor->nombre : '')),
            ],
            'message' => 'Bloque creado. Después podés completar cupos y detalles.',
        ]);
    }

    public function store(Request $request, VillaGesellAdmin $admin): RedirectResponse
    {
        $admin->guardarInscripcion(null, $request->validate($admin->reglasInscripcion()), $request->boolean('lista_espera'), $request->boolean('calcular_aporte'));

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Alumno inscripto en la gira.');
    }

    public function edit(VillaGesellInscripto $inscripto): View
    {
        $inscripto->load('alumno');
        $config = $this->gira->config();
        $alumnos = $this->alumnosDisponibles($inscripto->alumno_id);
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre')->get();
        $bloques = Bloque::query()->with(['sede', 'profesor'])->where('activo', true)->orderBy('nombre')->get();
        $profesores = Profesor::query()->where('activo', true)->orderBy('nombre')->get();

        return view('villa-gesell.inscriptos.edit', compact('inscripto', 'alumnos', 'config', 'sedes', 'bloques', 'profesores'));
    }

    public function update(Request $request, VillaGesellInscripto $inscripto, VillaGesellAdmin $admin): RedirectResponse
    {
        $admin->guardarInscripcion($inscripto, $request->validate($admin->reglasInscripcion($inscripto->id)), $request->boolean('lista_espera'), $request->boolean('calcular_aporte'));

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción actualizada.');
    }

    public function destroy(VillaGesellInscripto $inscripto): RedirectResponse
    {
        $inscripto->delete();

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción eliminada.');
    }

    private function alumnosDisponibles(?int $keepId = null)
    {
        $ocupados = VillaGesellInscripto::query()
            ->when($keepId, fn ($q) => $q->where('alumno_id', '!=', $keepId))
            ->pluck('alumno_id');

        return Alumno::query()
            ->whereNotIn('id', $ocupados)
            ->where(function ($q) use ($keepId) {
                $q->where('activo', true);
                if ($keepId) {
                    $q->orWhere('id', $keepId);
                }
            })
            ->orderBy('nombre_apellido')
            ->get();
    }
}
