<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importación de alumnos desde CSV o Excel. La usan el panel y la API.
 */
class AlumnoImportService
{
    public function __construct(private PersonaService $personas) {}

    /**
     * @return array{importados: int, omitidos: int, errores: list<string>}
     */
    public function importar(UploadedFile $archivo, int $sedeId, ?int $bloqueId): array
    {
        $ext = strtolower($archivo->getClientOriginalExtension());
        $filas = $this->leer($archivo->getRealPath() ?: '', $ext);
        if (count($filas) < 2) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene filas para importar.']);
        }

        $encabezados = $this->encabezados(array_shift($filas));
        $indices = $this->indices($encabezados);
        $importados = 0;
        $omitidos = 0;
        $errores = [];

        DB::transaction(function () use ($filas, $indices, $sedeId, $bloqueId, &$importados, &$omitidos, &$errores) {
            foreach ($filas as $i => $fila) {
                $linea = $i + 2;
                $mapped = $this->mapear($fila, $indices, $sedeId, $bloqueId);
                if (! $mapped) {
                    $omitidos++;
                    $errores[] = "Línea {$linea}: faltan datos obligatorios (nombre y/o fecha de nacimiento).";

                    continue;
                }
                $alumno = Alumno::updateOrCreate($this->buscar($mapped), array_diff_key($mapped, ['dni' => true, '_bloque_attach' => true]));
                if (! empty($mapped['dni'])) {
                    $alumno->dni = $mapped['dni'];
                    $alumno->save();
                }
                if (! empty($mapped['_bloque_attach'])) {
                    $alumno->bloques()->syncWithoutDetaching([$mapped['_bloque_attach'] => ['es_principal' => true]]);
                }
                $importados++;
            }
        });

        return ['importados' => $importados, 'omitidos' => $omitidos, 'errores' => $errores];
    }

    /** @return array<int, array<int, mixed>> */
    private function leer(string $path, string $ext): array
    {
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $hoja = Excel::toCollection(new class implements ToCollection
            {
                public function collection(\Illuminate\Support\Collection $rows) {}
            }, $path)->first();

            return $hoja ? $hoja->map(fn ($r) => is_array($r) ? $r : $r->toArray())->values()->all() : [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $primera = fgets($handle);
        if ($primera === false) {
            fclose($handle);

            return [];
        }
        $sep = str_contains($primera, ';') && ! str_contains($primera, ',') ? ';' : ',';
        $filas = [str_getcsv($primera, $sep)];
        while (($data = fgetcsv($handle, 0, $sep)) !== false) {
            $filas[] = $data;
        }
        fclose($handle);

        return $filas;
    }

    /** @param  array<int, mixed>  $headers @return array<int, string> */
    private function encabezados(array $headers): array
    {
        return array_map(function ($h) {
            $h = Str::lower(trim((string) $h));

            return preg_replace('/\s+/', ' ', $h) ?: $h;
        }, $headers);
    }

    /** @param  array<int, string>  $headers @return array<string, int> */
    private function indices(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $key = $this->clave($h);
            if ($key === 'tambor' && array_key_exists('tambor', $map)) {
                $key = 'tambor_1';
            }
            $map[$key] ??= $idx;
        }

        return $map;
    }

    private function clave(string $header): string
    {
        $h = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $header);
        $h = trim(preg_replace('/[^a-z0-9 ]/', '', $h) ?: $h);

        return match (true) {
            str_contains($h, 'nombre') && ! str_contains($h, 'bloque') && ! str_contains($h, 'sede') => 'nombre_apellido',
            $h === 'dni' || str_contains($h, 'documento') => 'dni',
            str_contains($h, 'fecha') && str_contains($h, 'nacimiento') => 'fecha_nacimiento',
            str_contains($h, 'telefono') || str_contains($h, 'celular') || str_contains($h, 'movil') => 'telefono',
            str_contains($h, 'procedencia') => 'tambor_1',
            str_contains($h, 'instrumento') || (str_contains($h, 'tipo') && str_contains($h, 'tambor')) || $h === 'tambor' => 'tambor',
            default => $h !== '' ? str_replace(' ', '_', $h) : 'col',
        };
    }

    /** @param  array<int, mixed>  $row @param  array<string, int>  $idx */
    private function celda(array $row, array $idx, string $key): string
    {
        return array_key_exists($key, $idx) ? trim((string) ($row[$idx[$key]] ?? '')) : '';
    }

    /** @param  array<int, mixed>  $row @param  array<string, int>  $idx @return array<string, mixed>|null */
    private function mapear(array $row, array $idx, int $sedeId, ?int $bloqueId): ?array
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $this->celda($row, $idx, 'nombre_apellido')) ?? '');
        $fecha = $this->fecha($this->celda($row, $idx, 'fecha_nacimiento'));
        if ($nombre === '' || ! $fecha) {
            return null;
        }
        $dni = preg_replace('/\D+/', '', $this->celda($row, $idx, 'dni')) ?: null;
        $telefono = $this->celda($row, $idx, 'telefono') ?: null;
        $tipo = $this->unoDe($this->celda($row, $idx, 'tambor'), AlumnoService::TIPOS_TAMBOR);

        return [
            'dni' => $dni,
            'nombre_apellido' => $nombre,
            'fecha_nacimiento' => $fecha->format('Y-m-d'),
            'telefono' => $telefono,
            'instrumento_principal' => $tipo ?? 'Otro',
            'instrumento_secundario' => null,
            'tipo_tambor' => $tipo,
            'tambor_procedencia' => $this->unoDe($this->celda($row, $idx, 'tambor_1'), AlumnoService::TAMBOR_PROCEDENCIAS),
            'bloque_id' => $bloqueId,
            'sede_id' => $sedeId,
            'activo' => true,
            '_bloque_attach' => $bloqueId,
        ];
    }

    /** @param  array<string, mixed>  $mapped @return array<string, mixed> */
    private function buscar(array $mapped): array
    {
        if (! empty($mapped['dni'])) {
            $existente = $this->personas->alumnoPorDni($mapped['dni']);

            return $existente ? ['id' => $existente->id] : ['dni' => $mapped['dni']];
        }

        return ['nombre_apellido' => $mapped['nombre_apellido'], 'sede_id' => $mapped['sede_id']];
    }

    private function fecha(string $raw): ?Carbon
    {
        $raw = preg_replace('/\s+/', '', str_replace(['.', '-'], '/', trim($raw))) ?? '';
        if ($raw === '') {
            return null;
        }
        foreach (['d/m/Y', 'd/m/y', 'j/n/Y', 'j/n/y'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt) {
                    return $dt;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** @param  array<int, string>  $permitidos */
    private function unoDe(string $valor, array $permitidos): ?string
    {
        $valor = trim($valor);
        foreach ($permitidos as $p) {
            if (Str::lower(trim($p)) === Str::lower($valor) && $valor !== '') {
                return $p;
            }
        }

        return null;
    }
}
