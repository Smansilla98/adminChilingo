<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Bloque;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class CuotaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Cuota::with(['bloque', 'sede']);
            if ($request->filled('año')) {
                $query->where('año', $request->año);
            }
            if ($request->filled('bloque_id')) {
                $bid = (int) $request->bloque_id;
                if (Schema::hasColumn('cuotas', 'alcance')) {
                    $bloqueFiltro = Bloque::query()->find($bid);
                    $sedeDelBloque = $bloqueFiltro?->sede_id;
                    $query->where(function ($q) use ($bid, $sedeDelBloque) {
                        $q->where('bloque_id', $bid)
                            ->orWhere('alcance', Cuota::ALCANCE_GENERAL);
                        if ($sedeDelBloque) {
                            $q->orWhere(function ($q2) use ($sedeDelBloque) {
                                $q2->where('alcance', Cuota::ALCANCE_SEDE)
                                    ->where('sede_id', $sedeDelBloque);
                            });
                        }
                    });
                } else {
                    $query->where('bloque_id', $bid);
                }
            }
            if ($request->filled('sede_id') && \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }
            if ($request->filled('alcance') && \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance')) {
                $query->where('alcance', $request->alcance);
            }
            $cuotas = $query->orderBy('año', 'desc')->orderBy('mes')->paginate(20);
            $bloques = Bloque::where('activo', true)->orderBy('nombre')->get();
            $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $cuotas = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $bloques = collect();
            $sedes = collect();
        }

        return view('cuotas.index', compact('cuotas', 'bloques', 'sedes'));
    }

    public function create()
    {
        try {
            $bloques = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
                $q->orderBy('nombre_apellido');
            }, 'sede'])->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }
        try {
            $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $sedes = collect();
        }
        try {
            $alumnosActivos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'sede_id']);
        } catch (QueryException $e) {
            $alumnosActivos = collect();
        }

        return view('cuotas.create', compact('bloques', 'sedes', 'alumnosActivos'));
    }

    public function store(Request $request)
    {
        $hasAlcance = \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance');
        $rules = [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'fecha_vencimiento' => 'nullable|date',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'alumno_ids' => 'nullable|array',
            'alumno_ids.*' => 'exists:alumnos,id',
        ];
        if ($hasAlcance) {
            $rules['mes'] = 'required|integer|min:1|max:12';
            $rules['alcance'] = 'required|in:bloque,sede,general';
            $rules['bloque_id'] = [
                Rule::requiredIf(fn () => $request->input('alcance') === Cuota::ALCANCE_BLOQUE),
                'nullable',
                'exists:bloques,id',
            ];
            $rules['sede_id'] = [
                Rule::requiredIf(fn () => $request->input('alcance') === Cuota::ALCANCE_SEDE),
                'nullable',
                'exists:sedes,id',
            ];
        } else {
            $rules['bloque_id'] = 'required|exists:bloques,id';
            $rules['mes'] = 'nullable|integer|min:1|max:12';
        }

        $validated = $request->validate($rules);
        $validated['activo'] = $request->has('activo');
        $alumnoIds = $validated['alumno_ids'] ?? [];
        unset($validated['alumno_ids']);

        if ($hasAlcance) {
            $alcance = $validated['alcance'];
            if ($alcance === Cuota::ALCANCE_GENERAL) {
                $validated['bloque_id'] = null;
                $validated['sede_id'] = null;
            } elseif ($alcance === Cuota::ALCANCE_SEDE) {
                $validated['bloque_id'] = null;
            } else {
                $validated['sede_id'] = null;
            }

            $this->assertCuotaUnicaEnPeriodo(
                $validated['año'],
                (int) ($validated['mes'] ?? 0),
                $alcance,
                $validated['bloque_id'] ?? null,
                $validated['sede_id'] ?? null,
                null
            );
        }

        $cuota = Cuota::create($validated);
        $cuota->alumnos()->sync(is_array($alumnoIds) ? array_filter($alumnoIds) : []);

        return redirect()->route('cuotas.index')->with('success', 'Cuota creada.');
    }

    public function show(Cuota $cuota)
    {
        $cuota->loadCount('pagoDetalles')->load(['bloque', 'sede', 'alumnos']);

        return view('cuotas.show', compact('cuota'));
    }

    public function edit(Cuota $cuota)
    {
        try {
            $bloques = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
                $q->orderBy('nombre_apellido');
            }, 'sede'])->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }
        try {
            $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $sedes = collect();
        }
        try {
            $alumnosActivos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'sede_id']);
        } catch (QueryException $e) {
            $alumnosActivos = collect();
        }
        $cuota->load('alumnos');

        return view('cuotas.edit', compact('cuota', 'bloques', 'sedes', 'alumnosActivos'));
    }

    public function update(Request $request, Cuota $cuota)
    {
        $hasAlcance = \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance');
        $rules = [
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'fecha_vencimiento' => 'nullable|date',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'alumno_ids' => 'nullable|array',
            'alumno_ids.*' => 'exists:alumnos,id',
        ];
        if ($hasAlcance) {
            $rules['mes'] = 'required|integer|min:1|max:12';
            $rules['alcance'] = 'required|in:bloque,sede,general';
            $rules['bloque_id'] = [
                Rule::requiredIf(fn () => $request->input('alcance') === Cuota::ALCANCE_BLOQUE),
                'nullable',
                'exists:bloques,id',
            ];
            $rules['sede_id'] = [
                Rule::requiredIf(fn () => $request->input('alcance') === Cuota::ALCANCE_SEDE),
                'nullable',
                'exists:sedes,id',
            ];
        } else {
            $rules['bloque_id'] = 'required|exists:bloques,id';
            $rules['mes'] = 'nullable|integer|min:1|max:12';
        }

        $validated = $request->validate($rules);
        $validated['activo'] = $request->has('activo');
        $alumnoIds = $validated['alumno_ids'] ?? [];
        unset($validated['alumno_ids']);

        if ($hasAlcance) {
            $alcance = $validated['alcance'];
            if ($alcance === Cuota::ALCANCE_GENERAL) {
                $validated['bloque_id'] = null;
                $validated['sede_id'] = null;
            } elseif ($alcance === Cuota::ALCANCE_SEDE) {
                $validated['bloque_id'] = null;
            } else {
                $validated['sede_id'] = null;
            }

            $this->assertCuotaUnicaEnPeriodo(
                $validated['año'],
                (int) ($validated['mes'] ?? 0),
                $alcance,
                $validated['bloque_id'] ?? null,
                $validated['sede_id'] ?? null,
                $cuota->id
            );
        }

        $cuota->update($validated);
        $cuota->alumnos()->sync(is_array($alumnoIds) ? array_filter($alumnoIds) : []);

        return redirect()->route('cuotas.index')->with('success', 'Cuota actualizada.');
    }

    public function destroy(Cuota $cuota)
    {
        $cuota->delete();

        return redirect()->route('cuotas.index')->with('success', 'Cuota eliminada.');
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertCuotaUnicaEnPeriodo(int $año, int $mes, string $alcance, ?int $bloqueId, ?int $sedeId, ?int $exceptId): void
    {
        if ($mes < 1) {
            return;
        }
        $q = Cuota::query()->where('año', $año)->where('mes', $mes)->where('alcance', $alcance);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        if ($alcance === Cuota::ALCANCE_BLOQUE) {
            $q->where('bloque_id', $bloqueId);
        } elseif ($alcance === Cuota::ALCANCE_SEDE) {
            $q->where('sede_id', $sedeId);
        } else {
            $q->whereNull('bloque_id')->whereNull('sede_id');
        }
        if ($q->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mes' => 'Ya existe una cuota de este tipo para ese mes y año.',
            ]);
        }
    }
}
