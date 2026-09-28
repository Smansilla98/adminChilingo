<?php

namespace App\Http\Controllers;

use App\Domain\Personas\FichaPersona;
use App\Domain\Personas\PersonaService;
use App\Http\Requests\PersonaRequest;
use App\Models\Beca;
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

    public function store(PersonaRequest $request, PersonaService $personas): RedirectResponse
    {
        $persona = $personas->crear($request->validated());

        return redirect()->route('personas.show', $persona)->with('success', 'Persona creada. Desde su ficha podés inscribirla, sumarla al plantel o darle una cuenta.');
    }

    public function show(Request $request, Persona $persona, FichaPersona $ficha): View
    {
        $this->authorize('view', $persona);
        $user = $request->user();
        $datos = $ficha->datos($persona, $user, $request->integer('anio') ?: null);

        return view('personas.show', $datos + [
            'persona' => $persona,
            'tiposBeca' => Beca::TIPOS,
            'puedeFusionar' => $user->can('merge', Persona::class),
        ]);
    }

    public function edit(Persona $persona): View
    {
        $this->authorize('update', $persona);

        return view('personas.form', compact('persona'));
    }

    public function update(PersonaRequest $request, Persona $persona, PersonaService $personas): RedirectResponse
    {
        $personas->actualizar($persona, $request->validated());

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
}
