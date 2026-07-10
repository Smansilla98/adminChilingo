<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RecordatorioChatbotService
{
    private const MAX_ASISTENCIAS = 6;

    private const MAX_CUOTAS_EJEMPLOS = 5;

    /**
     * @return array{saludo: string, mensajes: array<int, array<string, mixed>>, resumen: array<string, int>, todo_ok: bool}
     */
    public function build(User $user, ?int $maxAsistencias = null, ?int $maxCuotasEjemplos = null): array
    {
        try {
            return $this->buildInterno($user, $maxAsistencias, $maxCuotasEjemplos);
        } catch (\Throwable) {
            return [
                'saludo' => $this->saludo($user),
                'mensajes' => [[
                    'tipo' => 'info',
                    'prioridad' => 'baja',
                    'texto' => 'No pude revisar los pendientes en este momento. Probá de nuevo en un rato.',
                    'accion_url' => null,
                    'accion_texto' => null,
                ]],
                'resumen' => ['asistencias' => 0, 'cuotas' => 0, 'total' => 0],
                'todo_ok' => false,
            ];
        }
    }

    /**
     * Texto plano para envío por WhatsApp (resumen semanal).
     *
     * @param  array{saludo?: string, mensajes?: array<int, array<string, mixed>>, todo_ok?: bool}  $data
     */
    public function formatWhatsAppResumenSemanal(array $data): string
    {
        $inicio = now()->startOfWeek()->format('d/m');
        $fin = now()->endOfWeek()->format('d/m');

        $lines = ['ITO — Resumen semanal ('.$inicio.' al '.$fin.')', ''];

        $saludo = trim($data['saludo'] ?? '');
        if ($saludo !== '') {
            $lines[] = preg_replace('/hoy:?/i', 'esta semana:', $saludo);
            $lines[] = '';
        }

        if (! empty($data['todo_ok'])) {
            $lines[] = 'Todo al día: no hay asistencias atrasadas ni cuotas del mes sin registrar en el panel.';
        } else {
            foreach ($data['mensajes'] ?? [] as $mensaje) {
                if (($mensaje['tipo'] ?? '') === 'ok') {
                    continue;
                }
                $linea = '• '.($mensaje['texto'] ?? '');
                if (! empty($mensaje['accion_url'])) {
                    $linea .= "\n  ".$mensaje['accion_url'];
                }
                $lines[] = $linea;
            }
        }

        $lines[] = '';
        $lines[] = 'Panel: '.url('/dashboard');

        return implode("\n", $lines);
    }

    /**
     * Datos estructurados para el mail HTML del resumen diario.
     *
     * @return array<string, mixed>
     */
    public function buildMailPayload(User $user): array
    {
        $data = $this->build($user, 12, 8);
        $hoy = now();

        $asistencias = [];
        $cuotas = [];
        foreach ($data['mensajes'] ?? [] as $mensaje) {
            if (($mensaje['tipo'] ?? '') === 'ok') {
                continue;
            }
            $item = [
                'texto' => $mensaje['texto'] ?? '',
                'prioridad' => $mensaje['prioridad'] ?? 'baja',
                'url' => $mensaje['accion_url'] ?? null,
                'accion' => $mensaje['accion_texto'] ?? null,
            ];
            if (($mensaje['tipo'] ?? '') === 'asistencia') {
                $asistencias[] = $item;
            } elseif (($mensaje['tipo'] ?? '') === 'cuota') {
                $cuotas[] = $item;
            }
        }

        $resumen = $data['resumen'] ?? ['asistencias' => 0, 'cuotas' => 0, 'total' => 0];

        return [
            'fecha_larga' => ucfirst($hoy->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'fecha_corta' => $hoy->format('d/m/Y'),
            'saludo' => preg_replace('/hoy:?/i', 'hoy:', trim($data['saludo'] ?? '')),
            'todo_ok' => (bool) ($data['todo_ok'] ?? false),
            'resumen' => $resumen,
            'asistencias' => $asistencias,
            'cuotas' => $cuotas,
            'panel_url' => url('/dashboard'),
            'asistencias_url' => route('asistencias.index'),
            'pagos_url' => route('pagos.create'),
            'app_name' => config('app.name', 'ITO'),
        ];
    }

    /**
     * Texto plano para vista previa del mail.
     *
     * @param  array<string, mixed>  $payload
     */
    public function formatMailResumenTexto(array $payload): string
    {
        $lines = [
            ($payload['app_name'] ?? 'ITO').' — Resumen diario',
            'Fecha: '.($payload['fecha_corta'] ?? ''),
            '',
            $payload['saludo'] ?? '',
            '',
        ];

        if (! empty($payload['todo_ok'])) {
            $lines[] = 'Todo al día: no hay asistencias atrasadas ni cuotas del mes sin registrar.';
        } else {
            if (! empty($payload['asistencias'])) {
                $lines[] = 'ASISTENCIAS ('.($payload['resumen']['asistencias'] ?? 0).')';
                foreach ($payload['asistencias'] as $item) {
                    $lines[] = '- '.($item['texto'] ?? '');
                }
                $lines[] = '';
            }
            if (! empty($payload['cuotas'])) {
                $lines[] = 'CUOTAS ('.($payload['resumen']['cuotas'] ?? 0).')';
                foreach ($payload['cuotas'] as $item) {
                    $lines[] = '- '.($item['texto'] ?? '');
                }
                $lines[] = '';
            }
        }

        $lines[] = 'Panel: '.($payload['panel_url'] ?? url('/dashboard'));

        return implode("\n", $lines);
    }

    /**
     * @return array{saludo: string, mensajes: array<int, array<string, mixed>>, resumen: array<string, int>, todo_ok: bool}
     */
    private function buildInterno(User $user, ?int $maxAsistencias = null, ?int $maxCuotasEjemplos = null): array
    {
        $maxAsist = $maxAsistencias ?? self::MAX_ASISTENCIAS;
        $maxCuotas = $maxCuotasEjemplos ?? self::MAX_CUOTAS_EJEMPLOS;

        $mensajes = [];
        $asistencias = collect();
        $cuotasTotal = 0;

        if ($this->puedeVerAsistencias($user)) {
            $asistencias = $this->pendientesAsistencia($user);
            foreach ($asistencias->take($maxAsist) as $row) {
                $mensajes[] = [
                    'tipo' => 'asistencia',
                    'prioridad' => $row['estado'] === 'Pendiente' ? 'alta' : 'media',
                    'texto' => $row['texto'],
                    'accion_url' => $row['url'],
                    'accion_texto' => 'Cargar asistencia',
                ];
            }
            $extraAsist = $asistencias->count() - $maxAsist;
            if ($extraAsist > 0) {
                $mensajes[] = [
                    'tipo' => 'asistencia',
                    'prioridad' => 'media',
                    'texto' => 'Hay '.$extraAsist.' clase(s) más con asistencia incompleta o sin cargar.',
                    'accion_url' => $this->urlAsistenciasGeneral($user),
                    'accion_texto' => 'Ver asistencias',
                ];
            }
        }

        if ($this->puedeVerCuotas($user)) {
            $cuotas = $this->pendientesCuotas();
            $cuotasTotal = $cuotas['total'];
            if ($cuotasTotal > 0) {
                $mensajes[] = [
                    'tipo' => 'cuota',
                    'prioridad' => $cuotas['vencidas'] > 0 ? 'alta' : 'media',
                    'texto' => 'Hay '.$cuotasTotal.' cuota(s) del mes sin pago registrado en el sistema.',
                    'accion_url' => route('pagos.create'),
                    'accion_texto' => 'Registrar pago',
                ];
                foreach ($cuotas['ejemplos']->take($maxCuotas) as $ej) {
                    $mensajes[] = [
                        'tipo' => 'cuota',
                        'prioridad' => $ej['vencida'] ? 'alta' : 'baja',
                        'texto' => $ej['texto'],
                        'accion_url' => route('pagos.create'),
                        'accion_texto' => 'Ir a pagos',
                    ];
                }
                $extraCuotas = $cuotasTotal - min($cuotas['ejemplos']->count(), $maxCuotas);
                if ($extraCuotas > 0) {
                    $mensajes[] = [
                        'tipo' => 'cuota',
                        'prioridad' => 'baja',
                        'texto' => 'Y '.$extraCuotas.' más sin registrar. Podés cargarlas desde Pagos.',
                        'accion_url' => route('cuotas.index'),
                        'accion_texto' => 'Ver cuotas',
                    ];
                }
            }
        }

        $total = $asistencias->count() + $cuotasTotal;
        $todoOk = $total === 0;

        if ($todoOk) {
            $mensajes[] = [
                'tipo' => 'ok',
                'prioridad' => 'baja',
                'texto' => 'Por ahora no veo asistencias atrasadas ni cuotas del mes sin registrar. ¡Buen trabajo!',
                'accion_url' => null,
                'accion_texto' => null,
            ];
        }

        return [
            'saludo' => $this->saludo($user),
            'mensajes' => $mensajes,
            'resumen' => [
                'asistencias' => $asistencias->count(),
                'cuotas' => $cuotasTotal,
                'total' => $asistencias->count() + ($cuotasTotal > 0 ? 1 : 0),
            ],
            'todo_ok' => $todoOk,
        ];
    }

    private function saludo(User $user): string
    {
        $nombre = trim($user->name ?: $user->username ?: '');
        $hora = (int) now()->format('G');
        $prefijo = $hora < 12 ? 'Buen día' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

        return $nombre !== ''
            ? $prefijo.', '.$nombre.'. Te cuento qué conviene revisar hoy:'
            : $prefijo.'. Te cuento qué conviene revisar hoy:';
    }

    private function puedeVerAsistencias(User $user): bool
    {
        if (! Schema::hasTable('asistencias') || ! Schema::hasTable('bloques')) {
            return false;
        }
        if ($user->isAdmin()) {
            return $user->tieneAccesoModulo('admin.asistencias');
        }
        if ($user->isProfesor()) {
            return $user->tieneAccesoModulo('profesor.asistencia');
        }

        return false;
    }

    private function puedeVerCuotas(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }
        if (! Schema::hasTable('cuotas') || ! Schema::hasTable('pago_detalles')) {
            return false;
        }

        return $user->tieneAccesoModulo('admin.pagos') || $user->tieneAccesoModulo('admin.cuotas');
    }

    /**
     * @return Collection<int, array{estado: string, texto: string, url: string}>
     */
    private function pendientesAsistencia(User $user): Collection
    {
        $hoy = Carbon::today();
        $mes = (int) $hoy->month;
        $año = (int) $hoy->year;

        $bloques = $this->bloquesParaUsuario($user);
        if ($bloques->isEmpty()) {
            return collect();
        }

        $bloqueIds = $bloques->pluck('id')->all();
        $asistencias = Asistencia::query()
            ->whereIn('bloque_id', $bloqueIds)
            ->whereYear('fecha', $año)
            ->whereMonth('fecha', $mes)
            ->whereDate('fecha', '<=', $hoy->toDateString())
            ->get(['bloque_id', 'fecha', 'alumno_id'])
            ->groupBy(fn (Asistencia $a) => $a->bloque_id.'|'.$a->fecha->format('Y-m-d'));

        $filas = collect();

        foreach ($bloques as $bloque) {
            $bloque->loadMissing(['horarios', 'sede']);
            $totalAlumnos = (int) $bloque->alumnos()->where('activo', true)->count();
            if ($totalAlumnos === 0) {
                continue;
            }

            foreach ($this->fechasClaseDelMes($bloque, $año, $mes) as $fecha) {
                if ($fecha->gt($hoy)) {
                    continue;
                }

                $clave = $bloque->id.'|'.$fecha->format('Y-m-d');
                $regs = $asistencias->get($clave, collect());
                $regCount = $regs->pluck('alumno_id')->unique()->count();

                if ($regCount >= $totalAlumnos) {
                    continue;
                }

                $estado = $regCount === 0 ? 'Pendiente' : 'Incompleta';
                $fechaLabel = $fecha->locale('es')->isoFormat('ddd D/M');
                $bloqueLabel = $bloque->nombre.($bloque->sede ? ' ('.$bloque->sede->nombre.')' : '');

                $filas->push([
                    'estado' => $estado,
                    'texto' => $estado === 'Pendiente'
                        ? 'Falta cargar asistencia de '.$bloqueLabel.' — clase del '.$fechaLabel.'.'
                        : 'Asistencia incompleta en '.$bloqueLabel.' ('.$fechaLabel.'): '.$regCount.' de '.$totalAlumnos.' alumnos.',
                    'url' => $this->urlAsistenciaBloque($user, $bloque, $fecha),
                    '_fecha' => $fecha->timestamp,
                ]);
            }
        }

        return $filas
            ->sort(function (array $a, array $b) {
                if ($a['estado'] !== $b['estado']) {
                    return $a['estado'] === 'Pendiente' ? -1 : 1;
                }

                return ($b['_fecha'] ?? 0) <=> ($a['_fecha'] ?? 0);
            })
            ->values()
            ->map(fn ($r) => collect($r)->except('_fecha')->all());
    }

    /**
     * @return array{total: int, vencidas: int, ejemplos: Collection<int, array{texto: string, vencida: bool}>}
     */
    private function pendientesCuotas(): array
    {
        $hoy = Carbon::today();
        $mes = (int) $hoy->month;
        $año = (int) $hoy->year;

        $cuotasMes = Cuota::query()
            ->with([
                'alumnos' => fn ($q) => $q->where('activo', true)->with('sede'),
                'bloque.alumnos' => fn ($q) => $q->where('alumnos.activo', true),
                'bloque.sede',
                'sede',
            ])
            ->where('año', $año)
            ->where('mes', $mes)
            ->where('activo', true)
            ->get();

        if ($cuotasMes->isEmpty()) {
            return ['total' => 0, 'vencidas' => 0, 'ejemplos' => collect()];
        }

        $pagados = PagoDetalle::query()
            ->whereIn('cuota_id', $cuotasMes->pluck('id'))
            ->get(['alumno_id', 'cuota_id'])
            ->mapWithKeys(fn (PagoDetalle $pd) => [$pd->alumno_id.'-'.$pd->cuota_id => true]);

        $filas = collect();
        foreach ($cuotasMes as $cuota) {
            foreach ($this->alumnosObjetivoCuota($cuota) as $alumno) {
                if ($pagados->has($alumno->id.'-'.$cuota->id)) {
                    continue;
                }
                $fv = $cuota->fecha_vencimiento;
                $vencida = $fv ? $fv->copy()->startOfDay()->lt($hoy->copy()->subDays(5)) : false;
                $montoFmt = number_format((float) $cuota->monto, 0, ',', '.');
                $filas->push([
                    'texto' => $alumno->nombre_apellido.' — '.$cuota->nombre.' ($ '.$montoFmt.')',
                    'vencida' => $vencida,
                    '_sort' => $vencida ? 1 : 0,
                ]);
            }
        }

        $total = $filas->count();
        $vencidas = $filas->where('vencida', true)->count();
        $ejemplos = $filas
            ->sortByDesc('_sort')
            ->take(self::MAX_CUOTAS_EJEMPLOS)
            ->map(fn ($r) => ['texto' => $r['texto'], 'vencida' => $r['vencida']])
            ->values();

        return [
            'total' => $total,
            'vencidas' => $vencidas,
            'ejemplos' => $ejemplos,
        ];
    }

    /**
     * @return Collection<int, Bloque>
     */
    private function bloquesParaUsuario(User $user): Collection
    {
        $q = Bloque::query()->where('activo', true)->with('sede')->orderBy('nombre');

        if ($user->isProfesor() && ! $user->isAdmin()) {
            $prof = $user->profesor;
            $ids = $prof ? $prof->bloqueIdsDondeParticipa()->all() : [];
            $q->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $q->get();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function fechasClaseDelMes(Bloque $bloque, int $año, int $mes): Collection
    {
        $bloque->loadMissing('horarios');
        $dias = $bloque->horarios->pluck('dia_semana')->unique()->sort()->values();
        if ($dias->isEmpty()) {
            $dias = collect([5]);
        }

        $start = Carbon::createFromDate($año, $mes, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $out = collect();
        $d = $start->copy();
        while ($d->lte($end)) {
            if ($dias->contains($d->dayOfWeekIso)) {
                $out->push($d->copy());
            }
            $d->addDay();
        }

        return $out;
    }

    private function urlAsistenciaBloque(User $user, Bloque $bloque, Carbon $fecha): string
    {
        if ($user->isAdmin()) {
            return route('asistencias.index', [
                'bloque_id' => $bloque->id,
                'mes' => $fecha->month,
                'año' => $fecha->year,
            ]);
        }

        return route('profesor.asistencias.create', [
            'bloque_id' => $bloque->id,
            'fecha' => $fecha->format('Y-m-d'),
        ]);
    }

    private function urlAsistenciasGeneral(User $user): ?string
    {
        if ($user->isAdmin()) {
            return route('asistencias.index');
        }
        if ($user->isProfesor()) {
            return route('profesor.asistencias.create');
        }

        return null;
    }

    /**
     * @return Collection<int, Alumno>
     */
    private function alumnosObjetivoCuota(Cuota $cuota): Collection
    {
        if ($cuota->relationLoaded('alumnos') && $cuota->alumnos->isNotEmpty()) {
            return $cuota->alumnos
                ->where('activo', true)
                ->loadMissing('sede')
                ->unique('id')
                ->values();
        }

        $alcance = Schema::hasColumn('cuotas', 'alcance')
            ? $cuota->alcanceNormalizado()
            : Cuota::ALCANCE_BLOQUE;

        if ($alcance === Cuota::ALCANCE_GENERAL) {
            return Alumno::query()
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                })
                ->with('sede')
                ->orderBy('nombre_apellido')
                ->get();
        }

        if ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
            $sid = (int) $cuota->sede_id;

            return Alumno::query()
                ->where('activo', true)
                ->where(function ($q) use ($sid) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))
                        ->orWhere('sede_id', $sid);
                })
                ->with('sede')
                ->orderBy('nombre_apellido')
                ->get();
        }

        if (! $cuota->bloque_id) {
            return collect();
        }

        $bid = (int) $cuota->bloque_id;

        return Alumno::query()
            ->where('activo', true)
            ->where(function ($q) use ($bid) {
                if (Schema::hasTable('alumno_bloque')) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                } else {
                    $q->where('bloque_id', $bid);
                }
            })
            ->with('sede')
            ->orderBy('nombre_apellido')
            ->get();
    }
}
