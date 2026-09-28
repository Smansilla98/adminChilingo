<?php

namespace App\Http\Controllers;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Personas\ProfesorService;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProfesorController extends Controller
{
    public function index()
    {
        try {
            $query = Profesor::withCount('bloques')->orderBy('nombre');
            $alcance = auth()->user()->acceso()->alcance('profesores.view');
            if (! $alcance->esGlobal()) {
                // Plantel de las sedes/bloques donde tiene alcance.
                $query->where(fn ($q) => $q->whereHas('bloques', fn ($b) => $alcance->aplicarBloques($b))
                    ->orWhereHas('sedesConRol', fn ($ps) => $ps->whereIn('sedes.id', $alcance->sedesTocadas() ?: [0])));
            }
            $profesores = $query->paginate(20);
        } catch (QueryException $e) {
            $profesores = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return view('profesores.index', compact('profesores'));
    }

    public function create()
    {
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        $usuarios = $this->usuariosParaVincular();
        $hasUsername = $this->hasUsernameColumn();
        // Alta a partir de una persona existente (p. ej. un alumno que empieza a dar clase).
        $persona = request()->integer('persona_id') ? Persona::query()->with('user')->find(request()->integer('persona_id')) : null;

        return view('profesores.create', compact('bloquesParaAsignar', 'sedes', 'usuarios', 'hasUsername', 'persona'));
    }

    public function store(Request $request, ProfesorService $servicio)
    {
        $modo = (string) $request->input('cuenta_modo', 'ninguna');
        $validated = $request->validate($servicio->reglas($modo) + ['persona_id' => ['nullable', 'exists:personas,id']]);
        $validated['activo'] = $request->boolean('activo');
        $validated += $request->only(['login_password']);

        $servicio->crear($validated, $this->filasAsignacionesBloquesDesdeRequest($request), $this->filasSedeRolesDesdeRequest($request));

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor creado exitosamente.');
    }

    public function show(Profesor $profesor)
    {
        $this->authorize('view', $profesor);
        $profesor->load(['bloques.sede', 'sedesConRol', 'eventos', 'user', 'coordinadorAreas']);
        $alumnoPerfil = $profesor->alumnoPerfil();

        return view('profesores.show', compact('profesor', 'alumnoPerfil'));
    }

    public function edit(Profesor $profesor)
    {
        $this->authorize('update', $profesor);
        $profesor->load(['bloques', 'sedesConRol']);
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        $usuarios = $this->usuariosParaVincular($profesor->user_id);
        $hasUsername = $this->hasUsernameColumn();

        return view('profesores.edit', compact('profesor', 'bloquesParaAsignar', 'sedes', 'usuarios', 'hasUsername'));
    }

    public function update(Request $request, Profesor $profesor, ProfesorService $servicio)
    {
        $this->authorize('update', $profesor);
        $modo = (string) $request->input('cuenta_modo', $profesor->user_id ? 'existente' : 'ninguna');
        $validated = $request->validate($servicio->reglas($modo, $profesor->id));
        $validated['activo'] = $request->boolean('activo');
        $validated['cuenta_modo'] = $modo;
        $validated += $request->only(['login_password']);

        $servicio->actualizar($profesor, $validated, $this->filasAsignacionesBloquesDesdeRequest($request), $this->filasSedeRolesDesdeRequest($request));

        return redirect()->route('profesores.show', $profesor)
            ->with('success', 'Profesor actualizado exitosamente.');
    }

    public function destroy(Profesor $profesor, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $profesor);
        $eliminacion->verificar($profesor);
        $profesor->delete();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor eliminado exitosamente.');
    }

    /**
     * @return array<int, array{bloque_id: int, rol: string}>
     */
    private function filasAsignacionesBloquesDesdeRequest(Request $request): array
    {
        $filas = [];
        foreach ($request->input('asignaciones', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (empty($row['asignado'])) {
                continue;
            }
            $bid = (int) ($row['bloque_id'] ?? $key);
            if ($bid <= 0) {
                continue;
            }
            $rol = $row['rol'] ?? 'ayudante';
            if (! in_array($rol, Profesor::ROLES_BLOQUE, true)) {
                $rol = 'ayudante';
            }
            $filas[] = ['bloque_id' => $bid, 'rol' => $rol];
        }

        return $filas;
    }

    /**
     * @return array<int, array{sede_id: int, rol: string}>
     */
    private function filasSedeRolesDesdeRequest(Request $request): array
    {
        if (! Schema::hasTable('profesor_sede')) {
            return [];
        }

        $filas = [];
        foreach ($request->input('sede_roles', []) as $sedeId => $roles) {
            if (! is_array($roles)) {
                continue;
            }
            $sid = (int) $sedeId;
            if ($sid <= 0) {
                continue;
            }
            foreach ($roles as $rol => $on) {
                if (! $on) {
                    continue;
                }
                if (! array_key_exists($rol, Profesor::ROLES_SEDE)) {
                    continue;
                }
                $filas[] = ['sede_id' => $sid, 'rol' => (string) $rol];
            }
        }

        return $filas;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    private function usuariosParaVincular(?int $keepUserId = null)
    {
        $ocupados = Profesor::query()
            ->when($keepUserId, fn ($q) => $q->where('user_id', '!=', $keepUserId))
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return \App\Models\User::query()
            ->whereNotIn('id', $ocupados)
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    private function hasUsernameColumn(): bool
    {
        return app(ProfesorService::class)->hayUsername();
    }
}
