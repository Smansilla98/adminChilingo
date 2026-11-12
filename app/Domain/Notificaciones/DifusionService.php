<?php

namespace App\Domain\Notificaciones;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\Difusion;
use App\Models\Dispositivo;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mensajes a grupos armados a mano (difusión): toda la escuela, sedes, bloques, los convocados
 * a un show; a alumnos y/o docentes; opcionalmente solo alumnos con cuotas vencidas.
 *
 * Quien manda necesita `difusion.send`; con alcance de sede o bloque solo llega a su gente.
 * Cada aviso sale por NotificacionService con la clave «difusion:{id}:{user}»: nunca se repite.
 */
class DifusionService
{
    public const ALCANCES = [
        'escuela' => 'Toda la escuela',
        'sedes' => 'Una o varias sedes',
        'bloques' => 'Uno o varios bloques',
        'show' => 'Convocados a un show',
    ];

    public const CANALES = [
        'interna' => 'Bandeja de avisos',
        'push' => 'Notificación en el celular',
        'mail' => 'Correo',
        'whatsapp' => 'WhatsApp',
    ];

    public function __construct(private readonly NotificacionService $notificaciones) {}

    /** @return array<string, mixed> */
    public static function reglas(): array
    {
        return [
            'titulo' => 'required|string|max:120',
            'mensaje' => 'required|string|max:1000',
            'enlace' => ['nullable', 'string', 'max:255', 'regex:/^(app:\/\/[A-Za-z0-9_\-\/]+|https?:\/\/\S+)$/'],
            'canales' => 'required|array|min:1',
            'canales.*' => 'in:'.implode(',', array_keys(self::CANALES)),
            'alcance' => 'required|in:'.implode(',', array_keys(self::ALCANCES)),
            'sede_ids' => 'required_if:alcance,sedes|array',
            'sede_ids.*' => 'integer|exists:sedes,id',
            'bloque_ids' => 'required_if:alcance,bloques|array',
            'bloque_ids.*' => 'integer|exists:bloques,id',
            'show_id' => 'required_if:alcance,show|nullable|integer|exists:shows,id',
            'alumnos' => 'boolean',
            'docentes' => 'boolean',
            'solo_con_deuda' => 'boolean',
            'programada_para' => 'nullable|date|after:now',
            'confirmar_whatsapp' => 'boolean',
        ];
    }

    /**
     * Filtros normalizados a partir de los datos validados.
     *
     * @param  array<string, mixed>  $datos
     * @return array{alcance: string, sede_ids: list<int>, bloque_ids: list<int>, show_id: ?int, alumnos: bool, docentes: bool, solo_con_deuda: bool}
     */
    public static function filtros(array $datos): array
    {
        $alcance = (string) $datos['alcance'];
        $alumnos = (bool) ($datos['alumnos'] ?? true);

        return [
            'alcance' => $alcance,
            'sede_ids' => $alcance === 'sedes' ? array_values(array_unique(array_map('intval', (array) ($datos['sede_ids'] ?? [])))) : [],
            'bloque_ids' => $alcance === 'bloques' ? array_values(array_unique(array_map('intval', (array) ($datos['bloque_ids'] ?? [])))) : [],
            'show_id' => $alcance === 'show' && ! empty($datos['show_id']) ? (int) $datos['show_id'] : null,
            'alumnos' => $alumnos,
            'docentes' => (bool) ($datos['docentes'] ?? false),
            'solo_con_deuda' => $alumnos && (bool) ($datos['solo_con_deuda'] ?? false),
        ];
    }

    /**
     * Comprueba que quien manda llegue a todo el grupo elegido (403 si no).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function autorizar(User $user, array $filtros): void
    {
        $acceso = $user->acceso();
        abort_unless($acceso->puede('difusion.send'), 403);
        if ($acceso->puedeGlobal('difusion.send')) {
            return;
        }
        switch ($filtros['alcance']) {
            case 'escuela':
                abort(403, 'Solo dirección puede escribirle a toda la escuela. Elegí tu sede o tus bloques.');
            case 'sedes':
                foreach ($filtros['sede_ids'] as $id) {
                    abort_unless($acceso->puedeEnSede('difusion.send', (int) $id), 403, 'No podés escribirle a una sede fuera de tu alcance.');
                }
                break;
            case 'bloques':
                foreach ($filtros['bloque_ids'] as $id) {
                    abort_unless($acceso->puedeEnBloque('difusion.send', (int) $id), 403, 'No podés escribirle a un bloque fuera de tu alcance.');
                }
                break;
            case 'show':
                $show = Show::query()->find($filtros['show_id']);
                $bloques = $show ? $show->bloques()->get(['bloques.id', 'bloques.sede_id']) : collect();
                abort_if($bloques->isEmpty(), 403, 'Ese show no tiene bloques de tu alcance.');
                foreach ($bloques as $b) {
                    abort_unless($acceso->puedeEnBloque('difusion.send', (int) $b->id, $b->sede_id ? (int) $b->sede_id : null), 403, 'El show convoca bloques fuera de tu alcance.');
                }
                break;
        }
    }

    /**
     * Cuentas activas que van a recibir el mensaje, sin repetir.
     *
     * @param  array<string, mixed>  $filtros
     * @return EloquentCollection<int, User>
     */
    public function destinatarios(array $filtros): EloquentCollection
    {
        $usuarios = new EloquentCollection;
        $alcance = $filtros['alcance'];
        $show = $alcance === 'show' ? Show::query()->find($filtros['show_id']) : null;

        if ($filtros['alumnos']) {
            if ($show) {
                $alumnos = app(ConvocatoriaShow::class)->convocados($show);
            } else {
                $q = Alumno::query()->where('activo', true);
                if ($alcance === 'sedes') {
                    $ids = $filtros['sede_ids'] ?: [0];
                    $q->where(fn ($w) => $w->whereIn('sede_id', $ids)->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.sede_id', $ids)));
                } elseif ($alcance === 'bloques') {
                    $ids = $filtros['bloque_ids'] ?: [0];
                    $q->where(fn ($w) => $w->whereIn('bloque_id', $ids)->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $ids)));
                }
                $alumnos = $q->get();
            }
            if ($filtros['solo_con_deuda']) {
                $cuentas = app(EstadoCuentaService::class);
                $alumnos = $alumnos->filter(fn (Alumno $a) => $cuentas->paraAlumno($a)['totales']['vencido'] > 0);
            }
            $personaIds = $alumnos->pluck('persona_id')->filter()->unique()->values()->all();
            $userIds = $alumnos->pluck('user_id')->filter()->unique()->values()->all();
            if ($personaIds !== [] || $userIds !== []) {
                $usuarios = $usuarios->merge(User::query()->where('activo', true)
                    ->where(fn ($q) => $q->whereIn('persona_id', $personaIds ?: [0])->orWhereIn('id', $userIds ?: [0]))->get());
            }
        }

        if ($filtros['docentes']) {
            $profes = Profesor::query()->where('activo', true);
            $bloqueIds = match ($alcance) {
                'sedes' => Bloque::query()->whereIn('sede_id', $filtros['sede_ids'] ?: [0])->pluck('id')->all(),
                'bloques' => $filtros['bloque_ids'],
                'show' => $show ? $show->bloques()->pluck('bloques.id')->all() : [],
                default => null,
            };
            if ($bloqueIds !== null) {
                $profes->where(fn ($q) => $q->whereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bloqueIds ?: [0]))
                    ->orWhereIn('id', Bloque::query()->whereIn('id', $bloqueIds ?: [0])->whereNotNull('profesor_id')->select('profesor_id')));
            }
            $profes = $profes->get(['id', 'user_id', 'persona_id']);
            $usuarios = $usuarios->merge(User::query()->where('activo', true)->where(fn ($q) => $q
                ->whereIn('id', $profes->pluck('user_id')->filter()->all() ?: [0])
                ->orWhereIn('persona_id', $profes->pluck('persona_id')->filter()->all() ?: [0]))->get());
        }

        return $usuarios->unique('id')->sortBy('name')->values();
    }

    /**
     * Cuántos y cómo les llega, antes de mandar.
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $canales
     * @return array{cantidad: int, con_app: int, con_email: int, con_telefono: int, muestra: list<string>, whatsapp: array{cantidad: int, confirmar_desde: int, maximo: int, requiere_confirmacion: bool, supera_maximo: bool}}
     */
    public function vistaPrevia(array $filtros, array $canales): array
    {
        $users = $this->destinatarios($filtros);
        $users->loadMissing('persona');
        $conTelefono = $users->filter(fn (User $u) => filled($u->telefono ?? null) || filled($u->persona?->telefono))->count();

        return [
            'cantidad' => $users->count(),
            'con_app' => $users->isEmpty() ? 0 : Dispositivo::query()->whereIn('user_id', $users->pluck('id'))->distinct()->count('user_id'),
            'con_email' => $users->filter(fn (User $u) => filled($u->email) || filled($u->persona?->email))->count(),
            'con_telefono' => $conTelefono,
            'muestra' => $users->take(8)->map(fn (User $u) => (string) ($u->persona?->nombre_completo ?: $u->name))->values()->all(),
            'whatsapp' => $this->limiteWhatsapp(in_array('whatsapp', $canales, true) ? $conTelefono : 0),
        ];
    }

    /** @return array{cantidad: int, confirmar_desde: int, maximo: int, requiere_confirmacion: bool, supera_maximo: bool} */
    public function limiteWhatsapp(int $cantidad): array
    {
        $desde = max(1, (int) config('chilinga.difusion.whatsapp_confirmar_desde', 25));
        $maximo = max($desde, (int) config('chilinga.difusion.whatsapp_maximo', 300));

        return [
            'cantidad' => $cantidad,
            'confirmar_desde' => $desde,
            'maximo' => $maximo,
            'requiere_confirmacion' => $cantidad >= $desde,
            'supera_maximo' => $cantidad > $maximo,
        ];
    }

    /**
     * Crea la difusión; si no está programada para más tarde, la manda ya.
     *
     * @param  array<string, mixed>  $datos  validados con reglas()
     */
    public function crear(array $datos, User $por): Difusion
    {
        $filtros = self::filtros($datos);
        $this->autorizar($por, $filtros);
        $canales = array_values(array_intersect(array_keys(self::CANALES), (array) $datos['canales']));
        if (! $filtros['alumnos'] && ! $filtros['docentes']) {
            throw ValidationException::withMessages(['alumnos' => 'Elegí alumnos, docentes o ambos.']);
        }
        if (in_array('whatsapp', $canales, true)) {
            $wa = $this->vistaPrevia($filtros, $canales)['whatsapp'];
            if ($wa['supera_maximo']) {
                throw ValidationException::withMessages(['canales' => "Por WhatsApp se pueden mandar hasta {$wa['maximo']} mensajes por vez (cada uno tiene costo). Achicá el grupo o sacá WhatsApp."]);
            }
            if ($wa['requiere_confirmacion'] && empty($datos['confirmar_whatsapp'])) {
                throw ValidationException::withMessages(['confirmar_whatsapp' => "Van a salir {$wa['cantidad']} mensajes de WhatsApp, y cada uno tiene costo. Confirmá para seguir."]);
            }
        }

        $programada = ! empty($datos['programada_para']) ? Carbon::parse($datos['programada_para']) : null;
        $difusion = Difusion::query()->create([
            'titulo' => trim((string) $datos['titulo']),
            'mensaje' => trim((string) $datos['mensaje']),
            'enlace' => $datos['enlace'] ?? null,
            'canales' => $canales,
            'filtros' => $filtros,
            'estado' => 'programada',
            'programada_para' => $programada,
            'user_id' => $por->id,
        ]);
        Auditoria::registrar('difusion.crear', $difusion, null, ['titulo' => $difusion->titulo, 'filtros' => $filtros, 'canales' => $canales]);

        if (! $programada || $programada->lte(now())) {
            $this->enviar($difusion);
        }

        return $difusion->fresh() ?? $difusion;
    }

    /** Manda una difusión programada. No hace nada si ya salió o se canceló. */
    public function enviar(Difusion $difusion): Difusion
    {
        $tomada = Difusion::query()->whereKey($difusion->id)->where('estado', 'programada')->update(['estado' => 'enviando', 'updated_at' => now()]);
        if (! $tomada) {
            return $difusion->fresh() ?? $difusion;
        }
        $difusion->refresh();

        $resultado = array_fill_keys($difusion->canales, 0);
        $users = $this->destinatarios($difusion->filtros);
        $maximoWa = $this->limiteWhatsapp(0)['maximo'];
        $waEnviados = 0;
        foreach ($users as $user) {
            $canales = $difusion->canales;
            if (in_array('whatsapp', $canales, true) && $waEnviados >= $maximoWa) {
                $canales = array_values(array_diff($canales, ['whatsapp'])); // tope de costo
            }
            $r = $this->notificaciones->enviar($user, "difusion:{$difusion->id}:{$user->id}", [
                'tipo' => 'difusion',
                'titulo' => $difusion->titulo,
                'mensaje' => $difusion->mensaje,
                'enlace' => $difusion->enlace,
                'datos' => ['difusion_id' => $difusion->id],
            ], $canales);
            foreach ($r as $canal => $estado) {
                if ($estado === 'enviado') {
                    $resultado[$canal] = ($resultado[$canal] ?? 0) + 1;
                }
            }
            $waEnviados += (int) (($r['whatsapp'] ?? null) === 'enviado');
        }

        $difusion->update([
            'estado' => 'enviada',
            'enviada_at' => now(),
            'destinatarios' => $users->count(),
            'resultado' => $resultado,
        ]);

        return $difusion;
    }

    /** Las programadas cuya hora llegó (scheduler). */
    public function enviarPendientes(): int
    {
        $n = 0;
        Difusion::query()->where('estado', 'programada')->where('programada_para', '<=', now())->orderBy('programada_para')
            ->each(function (Difusion $d) use (&$n) {
                $this->enviar($d);
                $n++;
            });

        return $n;
    }

    public function cancelar(Difusion $difusion): void
    {
        abort_unless($difusion->estado === 'programada', 422, 'Solo se puede cancelar un mensaje que todavía no salió.');
        $difusion->update(['estado' => 'cancelada']);
        Auditoria::registrar('difusion.cancelar', $difusion);
    }

    /** Cuántos lo recibieron en la bandeja y cuántos lo abrieron. @return array{recibieron: int, leyeron: int} */
    public function lecturas(Difusion $difusion): array
    {
        $base = DB::table('notifications')->where('data->datos->difusion_id', $difusion->id);

        return [
            'recibieron' => (clone $base)->count(),
            'leyeron' => (clone $base)->whereNotNull('read_at')->count(),
        ];
    }

    /** ¿Puede ver esta difusión? Alcance global ve todas; el resto, las propias. */
    public function puedeVer(User $user, Difusion $difusion): bool
    {
        return $user->acceso()->puedeGlobal('difusion.send') || (int) $difusion->user_id === (int) $user->id;
    }

    /**
     * Opciones para armar el mensaje, acotadas al alcance de quien manda.
     *
     * @return array<string, mixed>
     */
    public function catalogo(User $user): array
    {
        $alcance = $user->acceso()->alcance('difusion.send');
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
            ->filter(fn (Sede $s) => $alcance->incluyeSede((int) $s->id))->values();
        $bloques = Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'sede_id'])
            ->filter(fn (Bloque $b) => $alcance->esGlobal() || $alcance->incluyeBloque((int) $b->id, $b->sede_id ? (int) $b->sede_id : null))->values();
        $bloqueIds = $bloques->pluck('id')->map(fn ($id) => (int) $id)->all();
        $shows = Show::query()->where('fecha', '>=', now()->subDays(7)->toDateString())->with('bloques:id')->orderBy('fecha')->get(['id', 'titulo', 'fecha'])
            ->filter(fn (Show $s) => $alcance->esGlobal() || ($s->bloques->isNotEmpty() && $s->bloques->every(fn ($b) => in_array((int) $b->id, $bloqueIds, true))))->values();

        return [
            'alcances' => $alcance->esGlobal() ? self::ALCANCES : array_diff_key(self::ALCANCES, ['escuela' => true]),
            'canales' => self::CANALES,
            'sedes' => $sedes->map(fn (Sede $s) => ['id' => (int) $s->id, 'nombre' => $s->nombre])->all(),
            'bloques' => $bloques->map(fn (Bloque $b) => ['id' => (int) $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre])->all(),
            'shows' => $shows->map(fn (Show $s) => ['id' => (int) $s->id, 'titulo' => $s->titulo, 'fecha' => $s->fecha->toDateString()])->all(),
            'whatsapp' => $this->limiteWhatsapp(0),
        ];
    }

    /**
     * Para listas y detalle (web y API).
     *
     * @return array<string, mixed>
     */
    public function presentar(Difusion $d, bool $detalle = false): array
    {
        $f = $d->filtros;
        $destino = match ($f['alcance'] ?? 'escuela') {
            'sedes' => 'Sedes: '.Sede::query()->whereIn('id', $f['sede_ids'] ?: [0])->pluck('nombre')->join(', '),
            'bloques' => 'Bloques: '.Bloque::query()->whereIn('id', $f['bloque_ids'] ?: [0])->pluck('nombre')->join(', '),
            'show' => 'Show: '.(Show::query()->whereKey($f['show_id'] ?? 0)->value('titulo') ?? '—'),
            default => 'Toda la escuela',
        };
        $quienes = implode(' y ', array_filter([
            ($f['alumnos'] ?? false) ? (($f['solo_con_deuda'] ?? false) ? 'alumnos con cuotas vencidas' : 'alumnos') : null,
            ($f['docentes'] ?? false) ? 'docentes' : null,
        ]));

        return [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'mensaje' => $d->mensaje,
            'enlace' => $d->enlace,
            'canales' => $d->canales,
            'canales_texto' => implode(', ', array_map(fn ($c) => self::CANALES[$c] ?? $c, $d->canales)),
            'destino' => $destino,
            'quienes' => $quienes,
            'filtros' => $f,
            'estado' => $d->estado,
            'estado_texto' => $d->etiquetaEstado(),
            'programada_para' => $d->programada_para?->toIso8601String(),
            'enviada_at' => $d->enviada_at?->toIso8601String(),
            'creada_at' => $d->created_at?->toIso8601String(),
            'destinatarios' => $d->destinatarios,
            'resultado' => $d->resultado ?? [],
            'autor' => $d->autor?->name,
            'lecturas' => $detalle || $d->estado === 'enviada' ? $this->lecturas($d) : null,
        ];
    }
}
