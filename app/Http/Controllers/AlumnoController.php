<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AlumnosExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;

class AlumnoController extends Controller
{
    private const TIPOS_TAMBOR = [
        'Redoblante',
        'Repique',
        'Medio',
        'Fondo Agudo',
        'Fondo Grave',
        'Timbal',
        'Platillo',
        'Otro',
    ];

    private const TAMBOR_PROCEDENCIAS = [
        'Propio',
        'Sede',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();

            $query = Alumno::with(['bloque', 'bloques', 'sede']);

            if ($user && $user->isProfesor() && !$user->isAdmin()) {
                $prof = $user->profesor;
                if ($prof) {
                    $query->where(function ($sub) use ($prof) {
                        $bloqueVisible = fn ($q) => $q->where('profesor_id', $prof->id)
                            ->orWhereHas('profesores', fn ($q2) => $q2->where('profesores.id', $prof->id));
                        $sub->whereHas('bloque', $bloqueVisible)
                            ->orWhereHas('bloques', $bloqueVisible);
                    });
                } else {
                    $query->whereRaw('1=0');
                }
            }

            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            if ($request->filled('bloque_id')) {
                $query->whereHas('bloques', function ($q) use ($request) {
                    $q->where('bloques.id', $request->bloque_id);
                });
            }

            if ($request->filled('activo')) {
                $query->where('activo', $request->activo === '1');
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre_apellido', 'like', "%{$search}%")
                      ->orWhere('dni', 'like', "%{$search}%");
                });
            }

            if ($request->filled('tipo_tambor')) {
                $query->where('tipo_tambor', $request->tipo_tambor);
            }

            if ($request->filled('tambor_procedencia')) {
                $query->where('tambor_procedencia', $request->tambor_procedencia);
            }

            $alumnos = $query->orderBy('nombre_apellido')->paginate(20);
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
            $tiposTambor = self::TIPOS_TAMBOR;
            $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;
        } catch (QueryException $e) {
            $alumnos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $sedes = collect();
            $bloques = collect();
            $tiposTambor = self::TIPOS_TAMBOR;
            $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;
        }

        return view('alumnos.index', compact('alumnos', 'sedes', 'bloques', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $bloques = collect();
        }
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        return view('alumnos.create', compact('sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:alumnos,dni|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'nullable|string|in:' . implode(',', self::TIPOS_TAMBOR),
            'tambor_procedencia' => 'nullable|string|in:' . implode(',', self::TAMBOR_PROCEDENCIAS),
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        $alumno = Alumno::create($validated);

        if (!empty($validated['bloque_id'])) {
            $alumno->bloques()->attach($validated['bloque_id'], ['es_principal' => true]);
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Alumno $alumno)
    {
        $user = auth()->user();
        if ($user && $user->isProfesor() && ! $user->isAdmin()) {
            $prof = $user->profesor;
            if (! $prof || ! $this->alumnoPerteneceABloquesDelProfesor($alumno, $prof)) {
                abort(403);
            }
        }

        $alumno->load(['bloque.profesor', 'bloques.profesor', 'sede', 'asistencias']);

        $bloquesIds = $alumno->bloques->pluck('id')->filter()->values();
        if ($bloquesIds->isEmpty() && $alumno->bloque_id) {
            $bloquesIds = collect([$alumno->bloque_id]);
        }

        $cuotas = collect();
        if ($bloquesIds->isNotEmpty()) {
            $q = Cuota::query()
                ->with(['alumnos:id'])
                ->where('activo', true);

            if (\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance')) {
                $sidList = $alumno->bloques->pluck('sede_id')->filter()->unique()->values()->all();
                if ($alumno->sede_id) {
                    $sidList = array_values(array_unique(array_merge($sidList, [(int) $alumno->sede_id])));
                }
                $q->where(function ($outer) use ($bloquesIds, $sidList) {
                    $outer->whereIn('bloque_id', $bloquesIds->all())
                        ->orWhere(function ($g) {
                            $g->where('alcance', Cuota::ALCANCE_GENERAL)->whereNull('bloque_id');
                        });
                    if ($sidList !== []) {
                        $outer->orWhere(function ($s) use ($sidList) {
                            $s->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sidList);
                        });
                    }
                });
            } else {
                $q->whereIn('bloque_id', $bloquesIds->all());
            }

            $cuotas = $q->orderByDesc('año')->orderByDesc('mes')->get();
        }

        $cuotasAplicables = $cuotas->filter(function (Cuota $cuota) use ($alumno) {
            if ($cuota->alumnos->isEmpty()) {
                return true;
            }
            return $cuota->alumnos->contains('id', $alumno->id);
        })->values();

        $pagosPorCuota = $cuotasAplicables->isNotEmpty()
            ? PagoDetalle::query()
                ->with(['pago:id,fecha_pago'])
                ->where('alumno_id', $alumno->id)
                ->whereIn('cuota_id', $cuotasAplicables->pluck('id')->all())
                ->get()
                ->keyBy('cuota_id')
            : collect();

        $hoy = Carbon::today();
        $estadoCuenta = $cuotasAplicables->map(function (Cuota $cuota) use ($pagosPorCuota, $hoy) {
            $pagoDetalle = $pagosPorCuota->get($cuota->id);

            $fechaPago = $pagoDetalle?->pago?->fecha_pago;
            $fechaVencimiento = $cuota->fecha_vencimiento;

            if ($fechaPago) {
                $estado = 'Pagada';
                $estadoColor = 'success';
            } elseif ($fechaVencimiento && $fechaVencimiento->lt($hoy)) {
                $estado = 'Vencida';
                $estadoColor = 'danger';
            } else {
                $estado = 'Pendiente';
                $estadoColor = 'warning';
            }

            return [
                'cuota' => $cuota,
                'periodo' => ($cuota->mes ? str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT) : '—') . '/' . ($cuota->año ?? '—'),
                'monto' => (float) $cuota->monto,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'estado_color' => $estadoColor,
            ];
        });

        return view('alumnos.show', compact('alumno', 'estadoCuenta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Alumno $alumno)
    {
        try {
            $sedes = Sede::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $bloques = collect();
        }
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;
        $tiposTambor = self::TIPOS_TAMBOR;
        $procedenciasTambor = self::TAMBOR_PROCEDENCIAS;

        return view('alumnos.edit', compact('alumno', 'sedes', 'bloques', 'instrumentos', 'tiposTambor', 'procedenciasTambor'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Alumno $alumno)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'nullable|string|unique:alumnos,dni,' . $alumno->id . '|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'nullable|string|in:' . implode(',', self::TIPOS_TAMBOR),
            'tambor_procedencia' => 'nullable|string|in:' . implode(',', self::TAMBOR_PROCEDENCIAS),
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        $alumno->update($validated);

        if (array_key_exists('bloque_id', $validated)) {
            $alumno->bloques()->sync(
                $validated['bloque_id']
                    ? [$validated['bloque_id'] => ['es_principal' => true]]
                    : []
            );
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alumno $alumno)
    {
        $alumno->delete();

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno eliminado exitosamente.');
    }

    /**
     * Exportar alumnos a Excel
     */
    public function export(Request $request)
    {
        return Excel::download(new AlumnosExport($request), 'alumnos_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function importForm()
    {
        $sedes = Sede::orderBy('nombre')->get();
        $bloques = Bloque::orderBy('nombre')->get();

        return view('alumnos.import', compact('sedes', 'bloques'));
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
        ], [
            'archivo.required' => 'Tenés que subir un archivo.',
            'archivo.mimes' => 'Formato inválido. Usá CSV o Excel.',
            'sede_id.required' => 'Seleccioná una sede.',
        ]);

        $file = $request->file('archivo');
        if (!$file) {
            throw ValidationException::withMessages(['archivo' => 'Archivo inválido.']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $rows = $this->readSpreadsheetRows($file->getRealPath(), $ext);

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene filas para importar.']);
        }

        $headers = $this->normalizeHeaders(array_shift($rows));
        $indexes = $this->buildIndexMap($headers);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $line = $i + 2; // + header
                $mapped = $this->mapAlumnoFromRow(
                    $row,
                    $indexes,
                    (int) $data['sede_id'],
                    !empty($data['bloque_id']) ? (int) $data['bloque_id'] : null
                );

                if (!$mapped) {
                    $skipped++;
                    $errors[] = "Línea {$line}: faltan datos obligatorios (nombre y/o fecha de nacimiento).";
                    continue;
                }

                $lookup = $this->buildAlumnoLookup($mapped);

                /** @var Alumno $alumno */
                $alumno = Alumno::updateOrCreate($lookup, array_diff_key($mapped, ['dni' => true, '_bloque_attach' => true]));

                if (!empty($mapped['dni'])) {
                    $alumno->dni = $mapped['dni'];
                    $alumno->save();
                }

                if (!empty($mapped['_bloque_attach'])) {
                    $alumno->bloques()->syncWithoutDetaching([
                        $mapped['_bloque_attach'] => ['es_principal' => true],
                    ]);
                }

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('alumnos.index')
            ->with('success', "Importación finalizada. Importados: {$imported}. Omitidos: {$skipped}.")
            ->with('import_errors', $errors);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheetRows(string $path, string $ext): array
    {
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $collection = Excel::toCollection(new class implements ToCollection {
                public function collection(\Illuminate\Support\Collection $rows) {}
            }, $path);

            $sheet = $collection->first();
            if (!$sheet) {
                return [];
            }

            return $sheet->map(fn ($r) => is_array($r) ? $r : $r->toArray())->values()->all();
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = str_contains($firstLine, ';') && !str_contains($firstLine, ',') ? ';' : ',';
        $rows[] = str_getcsv($firstLine, $delimiter);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param array<int, mixed> $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($h) {
            $h = trim((string) $h);
            $h = Str::lower($h);
            $h = preg_replace('/\s+/', ' ', $h) ?: $h;
            return $h;
        }, $headers);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function buildIndexMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $key = $this->headerKey($h);
            if ($key === 'tambor' && array_key_exists('tambor', $map)) {
                $key = 'tambor_1';
            }
            if (!array_key_exists($key, $map)) {
                $map[$key] = $idx;
            }
        }
        return $map;
    }

    private function headerKey(string $header): string
    {
        $h = $header;
        $h = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $h);
        $h = preg_replace('/[^a-z0-9 ]/', '', $h) ?: $h;
        $h = trim($h);

        return match (true) {
            str_contains($h, 'nombre') && str_contains($h, 'apellido') => 'nombre_apellido',
            str_contains($h, 'nombre') && ! str_contains($h, 'bloque') && ! str_contains($h, 'sede') => 'nombre_apellido',
            $h === 'dni' || str_contains($h, 'documento') => 'dni',
            str_contains($h, 'fecha') && str_contains($h, 'nacimiento') => 'fecha_nacimiento',
            str_contains($h, 'telefono') || str_contains($h, 'celular') || str_contains($h, 'movil') => 'telefono',
            str_contains($h, 'procedencia') => 'tambor_1',
            str_contains($h, 'tipo') && str_contains($h, 'tambor') => 'tambor',
            str_contains($h, 'instrumento') => 'tambor',
            $h === 'tambor' => 'tambor',
            default => $h !== '' ? str_replace(' ', '_', $h) : 'col',
        };
    }

    /**
     * Celda del archivo importado (columna opcional si no está en el encabezado).
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $idx
     */
    private function importCell(array $row, array $idx, string $key): string
    {
        if (! array_key_exists($key, $idx)) {
            return '';
        }

        return trim((string) ($row[$idx[$key]] ?? ''));
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int> $idx
     * @return array<string, mixed>|null
     */
    private function mapAlumnoFromRow(array $row, array $idx, int $sedeId, ?int $bloqueId): ?array
    {
        $name = trim(preg_replace('/\s+/', ' ', $this->importCell($row, $idx, 'nombre_apellido')));
        if ($name === '') {
            return null;
        }

        $fechaRaw = $this->importCell($row, $idx, 'fecha_nacimiento');
        $fecha = $this->parseFechaNacimiento($fechaRaw);
        if (!$fecha) {
            return null;
        }

        $dniRaw = $this->importCell($row, $idx, 'dni');
        $dni = preg_replace('/\D+/', '', $dniRaw);
        $dni = $dni !== '' ? $dni : null;

        $telefono = $this->importCell($row, $idx, 'telefono');
        $telefono = $telefono !== '' ? $telefono : null;

        $tipoTambor = $this->normalizeOneOf($this->importCell($row, $idx, 'tambor'), self::TIPOS_TAMBOR);
        $procedencia = $this->normalizeOneOf($this->importCell($row, $idx, 'tambor_1'), self::TAMBOR_PROCEDENCIAS);

        $instrumentoPrincipal = $tipoTambor && in_array($tipoTambor, self::TIPOS_TAMBOR, true) ? $tipoTambor : 'Otro';

        return [
            'dni' => $dni,
            'nombre_apellido' => $name,
            'fecha_nacimiento' => $fecha->format('Y-m-d'),
            'telefono' => $telefono,
            'instrumento_principal' => $instrumentoPrincipal,
            'instrumento_secundario' => null,
            'tipo_tambor' => $tipoTambor,
            'tambor_procedencia' => $procedencia,
            'bloque_id' => $bloqueId,
            'sede_id' => $sedeId,
            'activo' => true,
            '_bloque_attach' => $bloqueId,
        ];
    }

    /**
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>
     */
    private function buildAlumnoLookup(array $mapped): array
    {
        if (!empty($mapped['dni'])) {
            return ['dni' => $mapped['dni']];
        }
        return [
            'nombre_apellido' => $mapped['nombre_apellido'],
            'sede_id' => $mapped['sede_id'],
        ];
    }

    private function parseFechaNacimiento(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(['.', '-'], '/', $raw);
        $raw = preg_replace('/\s+/', '', $raw);

        foreach (['d/m/Y', 'd/m/y', 'j/n/Y', 'j/n/y'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt) {
                    return $dt;
                }
            } catch (\Throwable) {
                // seguir
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizeOneOf(string $value, array $allowed): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        foreach ($allowed as $a) {
            if (Str::lower(trim($a)) === Str::lower($v)) {
                return $a;
            }
        }
        return null;
    }

    private function alumnoPerteneceABloquesDelProfesor(Alumno $alumno, Profesor $profesor): bool
    {
        $ids = $profesor->bloqueIdsDondeParticipa()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return false;
        }
        if ($alumno->bloque_id && in_array((int) $alumno->bloque_id, $ids, true)) {
            return true;
        }
        if (Schema::hasTable('alumno_bloque')) {
            return $alumno->bloques()->whereIn('bloques.id', $ids)->exists();
        }

        return false;
    }
}
