<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Acceso\ResolvedorAcceso;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Domain\Personas\PersonaService;
use App\Models\Asistencia;
use App\Models\Beca;
use App\Models\Evento;
use App\Models\InventarioItem;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ficha central de la persona: identidad, funciones, cuenta, permisos, cuotas, pagos,
 * asistencias, eventos e inventario relacionado.
 */
class PersonaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Persona::class);
        $acceso = $request->user()->acceso();

        $query = Persona::query()
            ->whereNull('fusionada_en_id')
            ->with(['user:id,persona_id,username,activo', 'profesor:id,persona_id,activo', 'alumnos:id,persona_id,activo,sede_id'])
            ->buscar($request->input('q'));

        // Fuera del alcance global, se listan las personas cuyos perfiles caen en su alcance.
        $alcance = $acceso->alcance('personas.view');
        if (! $alcance->esGlobal()) {
            $query->where(function (Builder $q) use ($alcance) {
                $q->whereHas('alumnos', fn (Builder $a) => $alcance->aplicarAlumnos($a))
                    ->orWhereHas('profesores', fn (Builder $p) => $p->whereHas('bloques', fn (Builder $b) => $alcance->aplicarBloques($b)));
            });
        }

        match ($request->input('funcion')) {
            'alumno' => $query->whereHas('alumnos'),
            'profesor' => $query->whereHas('profesores'),
            'con_cuenta' => $query->whereHas('user'),
            'sin_cuenta' => $query->whereDoesntHave('user'),
            default => null,
        };

        $personas = $query->orderBy('nombre')->paginate(25)->withQueryString();

        return view('personas.index', compact('personas'));
    }

    public function create(): View
    {
        $this->authorize('create', Persona::class);

        return view('personas.form', ['persona' => new Persona(['estado' => 'activo'])]);
    }

    public function store(Request $request, PersonaService $personas): RedirectResponse
    {
        $this->authorize('create', Persona::class);
        $persona = $personas->crear($this->validar($request));

        return redirect()->route('personas.show', $persona)->with('success', 'Persona creada. Desde su ficha podés inscribirla, sumarla al plantel o darle una cuenta.');
    }

    public function show(Request $request, Persona $persona, PresentadorAcceso $presentador, EstadoCuentaService $estadoCuenta): View
    {
        $this->authorize('view', $persona);
        $persona->load(['user', 'profesor.bloques.sede', 'profesor.sedesConRol', 'alumnos.bloques.sede', 'alumnos.sede', 'fusionadaEn']);
        $user = $request->user();

        $acceso = app(ResolvedorAcceso::class)->paraPersona($persona);
        $funciones = $presentador->funciones($acceso);
        $permisos = $persona->user && $user->can('view', $persona->user) && $user->acceso()->puede('usuarios.view')
            ? $presentador->permisosAgrupados($acceso)
            : null;

        $alumnos = $persona->alumnos;
        $cuentas = [];
        foreach ($alumnos as $alumno) {
            if ($user->can('verFinanzas', $alumno)) {
                $cuentas[$alumno->id] = $estadoCuenta->paraAlumno($alumno, $request->integer('anio') ?: null);
            }
        }

        $alumnoIds = $alumnos->pluck('id')->all();
        $asistencias = $alumnoIds === [] ? collect() : Asistencia::query()
            ->whereIn('alumno_id', $alumnoIds)
            ->with('bloque:id,nombre')
            ->orderByDesc('fecha')
            ->limit(20)
            ->get();

        $sedesPersona = collect($funciones)->pluck('sede_id')->filter()->unique()->values()->all();
        $eventos = Evento::query()
            ->where('fecha', '>=', now()->toDateString())
            ->where(fn ($q) => $q->whereIn('sede_id', $sedesPersona ?: [0])->orWhere(fn ($g) => $g->whereNull('sede_id')->whereNull('bloque_id')))
            ->orderBy('fecha')
            ->limit(8)
            ->get();

        $inventario = $alumnoIds === [] ? collect() : InventarioItem::query()->whereIn('alumno_id', $alumnoIds)->with('sede:id,nombre')->get();

        return view('personas.show', [
            'persona' => $persona,
            'funciones' => $funciones,
            'permisos' => $permisos,
            'cuentas' => $cuentas,
            'asistencias' => $asistencias,
            'eventos' => $eventos,
            'inventario' => $inventario,
            'tiposBeca' => Beca::TIPOS,
            'puedeFusionar' => $user->can('merge', Persona::class),
        ]);
    }

    public function edit(Persona $persona): View
    {
        $this->authorize('update', $persona);

        return view('personas.form', compact('persona'));
    }

    public function update(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('update', $persona);
        $datos = $this->validar($request, $persona);
        $datos['dni'] = Persona::normalizarDni($datos['dni'] ?? null);
        $persona->update($datos);

        return redirect()->route('personas.show', $persona)->with('success', 'Datos actualizados en todas sus fichas.');
    }

    public function fusionar(Request $request, Persona $persona, PersonaService $personas): RedirectResponse
    {
        $this->authorize('merge', Persona::class);
        $data = $request->validate(['duplicada_id' => 'required|integer|exists:personas,id|different:persona_id']);
        $duplicada = Persona::query()->findOrFail($data['duplicada_id']);
        $resultado = $personas->fusionar($persona, $duplicada);

        return redirect()->route('personas.show', $resultado)->with('success', 'Personas fusionadas: se unificaron fichas, cuenta y asignaciones.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Persona $persona = null): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'dni' => 'nullable|string|max:20|unique:personas,dni'.($persona ? ','.$persona->id : ''),
            'fecha_nacimiento' => 'nullable|date|before:today',
            'telefono' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'contacto_emergencia_nombre' => 'nullable|string|max:255',
            'contacto_emergencia_telefono' => 'nullable|string|max:40',
            'estado' => 'required|in:'.implode(',', array_keys(Persona::ESTADOS)),
            'observaciones' => 'nullable|string|max:2000',
        ], ['dni.unique' => 'Ya hay otra persona con ese DNI. Si es la misma, fusionalas desde su ficha.']);
    }
}
