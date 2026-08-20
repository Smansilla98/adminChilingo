<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Sede;
use App\Models\VillaGesellInscripto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $inscripto = new VillaGesellInscripto([
            'estado_pago' => 'pendiente',
            'fecha_desde' => $config->fecha_inicio,
            'fecha_hasta' => $config->fecha_fin,
            'plaza' => $this->gira->plazaDisponible(),
        ]);
        $inscripto->monto_esperado = $inscripto->aporteSegunDias($config->valorPorDia());

        return view('villa-gesell.inscriptos.create', compact('alumnos', 'config', 'inscripto', 'sedes'));
    }

    public function storeAlumnoRapido(Request $request): JsonResponse
    {
        $request->merge([
            'dni' => filled($request->input('dni')) ? trim((string) $request->input('dni')) : null,
            'telefono' => filled($request->input('telefono')) ? trim((string) $request->input('telefono')) : null,
            'fecha_nacimiento' => filled($request->input('fecha_nacimiento')) ? $request->input('fecha_nacimiento') : null,
            'sede_id' => filled($request->input('sede_id')) ? $request->input('sede_id') : null,
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
            'instrumento_principal' => ['nullable', 'string', 'max:80'],
        ], [
            'nombre_apellido.required' => 'El nombre es obligatorio.',
            'dni.unique' => 'Ese DNI ya está cargado en el padrón.',
        ]);

        $alumno = Alumno::query()->create([
            'nombre_apellido' => trim($data['nombre_apellido']),
            'dni' => $data['dni'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'sede_id' => $data['sede_id'] ?? null,
            'instrumento_principal' => $data['instrumento_principal'] ?? 'Otro',
            'activo' => true,
        ]);

        return response()->json([
            'ok' => true,
            'alumno' => [
                'id' => $alumno->id,
                'nombre_apellido' => $alumno->nombre_apellido,
                'dni' => $alumno->dni,
            ],
            'message' => 'Alumno creado. Ya lo podés inscribir a la gira.',
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

        return view('villa-gesell.inscriptos.edit', compact('inscripto', 'alumnos', 'config', 'sedes'));
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
