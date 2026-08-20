<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\VillaGesellInscripto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
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
        $inscripto = new VillaGesellInscripto([
            'estado_pago' => 'pendiente',
            'fecha_desde' => $config->fecha_inicio,
            'fecha_hasta' => $config->fecha_fin,
            'plaza' => $this->gira->plazaDisponible(),
        ]);
        $inscripto->monto_esperado = $inscripto->aporteSegunDias($config->valorPorDia());

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

        $data = $request->validate([
            'nombre_apellido' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:20', 'unique:alumnos,dni'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
            'profesor_id' => ['nullable', 'exists:profesores,id'],
            'instrumento_principal' => ['nullable', 'string', 'max:80'],
        ], [
            'nombre_apellido.required' => 'El nombre es obligatorio.',
            'dni.unique' => 'Ese DNI ya está cargado en el padrón.',
        ]);

        $bloque = ! empty($data['bloque_id'])
            ? Bloque::query()->with('profesor')->find((int) $data['bloque_id'])
            : null;

        if ($bloque && empty($data['sede_id']) && $bloque->sede_id) {
            $data['sede_id'] = $bloque->sede_id;
        }

        // Si eligió profesor pero no bloque, tomar un bloque activo de ese profe.
        if (! $bloque && ! empty($data['profesor_id'])) {
            $bloque = Bloque::query()
                ->where('activo', true)
                ->where('profesor_id', (int) $data['profesor_id'])
                ->orderBy('nombre')
                ->first();
            if ($bloque && empty($data['sede_id']) && $bloque->sede_id) {
                $data['sede_id'] = $bloque->sede_id;
            }
        }

        $alumno = Alumno::query()->create([
            'nombre_apellido' => trim($data['nombre_apellido']),
            'dni' => $data['dni'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'sede_id' => $data['sede_id'] ?? null,
            'bloque_id' => $bloque?->id,
            'instrumento_principal' => $data['instrumento_principal'] ?? 'Otro',
            'activo' => true,
        ]);

        if ($bloque && Schema::hasTable('alumno_bloque')) {
            $alumno->bloques()->sync([
                $bloque->id => ['es_principal' => true],
            ]);
        }

        $profesorNombre = $bloque?->profesor?->nombre
            ?? (isset($data['profesor_id']) ? Profesor::query()->find((int) $data['profesor_id'])?->nombre : null);

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

        $profesor = Profesor::query()->create([
            'nombre' => trim($data['nombre']),
            'telefono' => filled($data['telefono'] ?? null) ? trim((string) $data['telefono']) : null,
            'activo' => true,
        ]);

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

        $sedeId = $data['sede_id'] ?? Sede::query()->where('activo', true)->orderBy('nombre')->value('id');
        if (! $sedeId) {
            return response()->json([
                'message' => 'Necesitás al menos una sede activa para crear un bloque. Cargala en Sedes y volvé.',
            ], 422);
        }

        $bloque = Bloque::query()->create([
            'nombre' => trim($data['nombre']),
            'año' => 1,
            'sede_id' => $sedeId,
            'profesor_id' => $data['profesor_id'] ?? null,
            'cantidad_max_alumnos' => 40,
            'activo' => true,
        ]);
        if (method_exists($bloque, 'syncProfesorTitularEnPivot')) {
            $bloque->syncProfesorTitularEnPivot();
        }
        $bloque->load(['sede', 'profesor']);

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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->gira->validarPlaza($data['lista_espera'] ? null : $data['plaza'], null);
        if ($data['lista_espera']) {
            $data['plaza'] = null;
        }

        VillaGesellInscripto::query()->create($data);

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

    public function update(Request $request, VillaGesellInscripto $inscripto): RedirectResponse
    {
        $data = $this->validated($request, $inscripto->id);
        $this->gira->validarPlaza($data['lista_espera'] ? null : $data['plaza'], $inscripto->id);
        if ($data['lista_espera']) {
            $data['plaza'] = null;
        }
        $inscripto->update($data);

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción actualizada.');
    }

    public function destroy(VillaGesellInscripto $inscripto): RedirectResponse
    {
        $inscripto->delete();

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $config = $this->gira->config();
        $data = $request->validate([
            'alumno_id' => [
                'required',
                'exists:alumnos,id',
                Rule::unique('villa_gesell_inscriptos', 'alumno_id')->ignore($exceptId),
            ],
            'estado_pago' => ['required', Rule::in(array_keys(VillaGesellInscripto::ESTADOS_PAGO))],
            'monto_esperado' => ['required', 'numeric', 'min:0'],
            'monto_pagado' => ['required', 'numeric', 'min:0'],
            'plaza' => ['nullable', 'integer', 'min:1', 'max:'.$config->cupo_maximo],
            'lista_espera' => ['sometimes', 'boolean'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'talle_remera' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::TALLES))],
            'tambor_principal' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_secundario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_terciario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_principal_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_secundario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_terciario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'notas' => ['nullable', 'string', 'max:4000'],
        ]);
        $data['lista_espera'] = $request->boolean('lista_espera');
        $data['plaza'] = $data['plaza'] !== null ? (int) $data['plaza'] : null;

        if ($request->boolean('calcular_aporte')) {
            $tmp = new VillaGesellInscripto([
                'fecha_desde' => $data['fecha_desde'] ?? null,
                'fecha_hasta' => $data['fecha_hasta'] ?? null,
            ]);
            $data['monto_esperado'] = $tmp->aporteSegunDias($config->valorPorDia());
        }

        return $data;
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
