<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportChilingaExcelCommand extends Command
{
    protected $signature = 'chilinga:import-excel 
                            {file : Ruta al archivo .xlsx} 
                            {--fresh : Vaciar tablas relacionadas antes (sedes, bloques, profesores, alumnos, cuotas)}
                            {--dry-run : Solo mostrar qué se importaría, sin escribir en la base}';

    protected $description = 'Importa datos del Excel Chilinga 2025 (Formulario, Cuotas, bloques)';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path) || !pathinfo($path, PATHINFO_EXTENSION)) {
            $this->error('El archivo no existe o no es válido: ' . $path);
            return self::FAILURE;
        }

        $this->info('Cargando: ' . $path);
        $spreadsheet = IOFactory::load($path);
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá en la base de datos.');
        }

        if (!$dryRun && $this->option('fresh')) {
            if (!$this->confirm('¿Vaciar alumnos, cuotas, alumno_bloque, bloques, profesores, sedes y volver a importar?')) {
                return self::SUCCESS;
            }
            $this->vaciarTablas();
        }

        if ($dryRun) {
            $this->dryRun($spreadsheet);
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $sedesMap = $this->importSedes($spreadsheet);
            $profesor = $this->importProfesor($spreadsheet);
            $bloque = $this->importBloque($spreadsheet, $sedesMap, $profesor);
            $alumnosMap = $this->importAlumnos($spreadsheet, $sedesMap, $bloque);
            $this->importCuotas($spreadsheet, $bloque);
            DB::commit();
            $this->info('Importación completada.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function vaciarTablas(): void
    {
        $this->warn('Vaciando tablas...');
        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('pago_detalles')) {
                DB::table('pago_detalles')->truncate();
            }
            if (Schema::hasTable('alumno_bloque')) {
                DB::table('alumno_bloque')->truncate();
            }
            if (Schema::hasTable('asistencias')) {
                DB::table('asistencias')->truncate();
            }
            Alumno::query()->delete();
            Cuota::query()->delete();
            Bloque::query()->delete();
            Profesor::query()->delete();
            Sede::query()->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $this->info('Tablas vaciadas.');
    }

    private function dryRun(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $sheet = $this->getSheetByName($spreadsheet, 'Formulario');
        $sedes = [];
        $count = 0;
        if ($sheet) {
            for ($r = 2; $r <= $sheet->getHighestRow(); $r++) {
                $nombre = trim((string) $sheet->getCell('B' . $r)->getValue());
                if ($nombre !== '') {
                    $count++;
                    $s = trim((string) $sheet->getCell('G' . $r)->getValue());
                    if ($s !== '') {
                        $sedes[$s] = true;
                    }
                }
            }
        }
        $this->info('Sedes a crear: ' . implode(', ', array_keys($sedes)) ?: '(una por defecto)');
        $this->info('Alumnos a importar: ' . $count);
        $sheetCuotas = $this->getSheetByName($spreadsheet, 'Cuotas');
        if ($sheetCuotas) {
            $meses = [];
            for ($c = 3; $c <= 12; $c++) {
                $m = trim((string) $sheetCuotas->getCellByColumnAndRow($c, 3)->getValue());
                if ($m !== '') {
                    $meses[] = $m;
                }
            }
            $this->info('Cuotas (meses): ' . implode(', ', $meses));
        }
        $this->info('Bloque: Trinchera Sur (desde Hoja 4 / RemerasBloque)');
        $this->info('Profesor: desde RemerasBloque (ej. Santi Mansilla)');
    }

    private function getSheetByName(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $name): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (trim($sheet->getTitle()) === $name) {
                return $sheet;
            }
        }
        return null;
    }

    private function importSedes(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $sheet = $this->getSheetByName($spreadsheet, 'Formulario');
        if (!$sheet) {
            $this->warn('Hoja Formulario no encontrada. Se crea una sede por defecto.');
            $sede = Sede::firstOrCreate(
                ['nombre' => 'Sede principal'],
                ['direccion' => '', 'activo' => true]
            );
            return ['Sede principal' => $sede->id, 'Propio' => $sede->id];
        }

        $sedesMap = [];
        $uniqueSedes = [];
        for ($r = 2; $r <= $sheet->getHighestRow(); $r++) {
            $val = trim((string) $sheet->getCell('G' . $r)->getValue());
            if ($val !== '' && $val !== 'Sede' && !isset($uniqueSedes[$val])) {
                $uniqueSedes[$val] = true;
            }
        }
        if (empty($uniqueSedes)) {
            $uniqueSedes = ['Propio' => true, 'Sede' => true];
        }
        if (!isset($uniqueSedes['Sede'])) {
            $uniqueSedes['Sede'] = true;
        }

        foreach (array_keys($uniqueSedes) as $nombre) {
            $sede = Sede::firstOrCreate(
                ['nombre' => $nombre],
                ['direccion' => '', 'activo' => true]
            );
            $sedesMap[$nombre] = $sede->id;
        }

        $this->info('Sedes: ' . count($sedesMap));
        return $sedesMap;
    }

    private function importProfesor(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): ?Profesor
    {
        $sheet = $this->getSheetByName($spreadsheet, 'RemerasBloque');
        $nombreProfesor = 'Santi Mansilla';
        if ($sheet) {
            $val = trim((string) $sheet->getCell('A2')->getValue());
            if (stripos($val, 'Profe:') === 0) {
                $nombreProfesor = trim(str_ireplace('Profe:', '', $val));
                if ($nombreProfesor === '') {
                    $nombreProfesor = 'Santi Mansilla';
                }
            }
        }
        $profesor = Profesor::firstOrCreate(
            ['nombre' => $nombreProfesor],
            ['activo' => true]
        );
        $this->info('Profesor: ' . $profesor->nombre);
        return $profesor;
    }

    private function importBloque(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $sedesMap, ?Profesor $profesor): ?Bloque
    {
        $nombreBloque = 'Trinchera Sur';
        $sedeId = reset($sedesMap);

        $sheet = $this->getSheetByName($spreadsheet, 'Hoja 4');
        if ($sheet) {
            $val = trim((string) $sheet->getCell('B1')->getValue());
            if (stripos($val, 'Bloque:') === 0) {
                $nombreBloque = trim(str_ireplace('Bloque:', '', $val));
            }
        }

        $bloque = Bloque::firstOrCreate(
            [
                'nombre' => $nombreBloque,
                'sede_id' => $sedeId,
            ],
            [
                'año' => 2025,
                'profesor_id' => $profesor?->id,
                'activo' => true,
            ]
        );
        $this->info('Bloque: ' . $bloque->nombre);
        return $bloque;
    }

    private function importAlumnos(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $sedesMap, ?Bloque $bloque): array
    {
        $sheet = $this->getSheetByName($spreadsheet, 'Formulario');
        if (!$sheet) {
            return [];
        }

        $alumnosMap = [];
        $highest = $sheet->getHighestRow();
        for ($r = 2; $r <= $highest; $r++) {
            $nombre = trim((string) $sheet->getCell('B' . $r)->getValue());
            if ($nombre === '') {
                continue;
            }
            $dni = $sheet->getCell('C' . $r)->getValue();
            if (is_numeric($dni)) {
                $dni = (string) (int) $dni;
            } else {
                $dni = trim((string) $dni);
            }
            $fechaNac = $sheet->getCell('D' . $r)->getValue();
            $fechaNacimiento = null;
            if (is_numeric($fechaNac)) {
                try {
                    $fechaNacimiento = ExcelDate::excelToDateTimeObject($fechaNac)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $telefono = $sheet->getCell('E' . $r)->getValue();
            if (is_numeric($telefono)) {
                $telefono = (string) $telefono;
            } else {
                $telefono = trim((string) $telefono) ?: null;
            }
            $tambor = trim((string) $sheet->getCell('F' . $r)->getValue()) ?: null;
            $sedeNombre = trim((string) $sheet->getCell('G' . $r)->getValue());
            if ($sedeNombre === '' || $sedeNombre === 'Sede') {
                $sedeNombre = array_key_first($sedesMap);
            }
            $sedeId = $sedesMap[$sedeNombre] ?? reset($sedesMap);

            $alumno = Alumno::firstOrCreate(
                ['dni' => $dni ?: ('excel-' . $r)],
                [
                    'nombre_apellido' => $nombre,
                    'fecha_nacimiento' => $fechaNacimiento ?? '1990-01-01',
                    'telefono' => $telefono,
                    'instrumento_principal' => $tambor,
                    'sede_id' => $sedeId,
                    'activo' => true,
                ]
            );

            if ($bloque && !$alumno->bloques()->where('bloques.id', $bloque->id)->exists()) {
                $alumno->bloques()->attach($bloque->id, ['es_principal' => true]);
            }
            $alumnosMap[$r] = $alumno;
        }

        $this->info('Alumnos importados: ' . count($alumnosMap));
        return $alumnosMap;
    }

    private function importCuotas(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, ?Bloque $bloque): void
    {
        $sheet = $this->getSheetByName($spreadsheet, 'Cuotas');
        if (!$sheet || !$bloque) {
            return;
        }

        $meses = [
            3 => 'Marzo', 4 => 'Abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre',
            11 => 'noviembre', 12 => 'diciembre',
        ];
        $año = 2025;
        $created = 0;
        for ($col = 3; $col <= 12; $col++) {
            $nombreMes = trim((string) $sheet->getCellByColumnAndRow($col, 3)->getValue());
            if ($nombreMes === '') {
                continue;
            }
            $mesNum = array_search($nombreMes, $meses, true) ?: $col;
            $monto = 15000;
            $cell = $sheet->getCellByColumnAndRow($col, 4)->getValue();
            if (is_numeric($cell)) {
                $monto = (float) $cell;
            }
            $nombre = "Cuota {$nombreMes} {$año}";
            $cuota = Cuota::firstOrCreate(
                [
                    'bloque_id' => $bloque->id,
                    'nombre' => $nombre,
                    'año' => $año,
                    'mes' => $mesNum,
                ],
                [
                    'monto' => $monto,
                    'activo' => true,
                ]
            );
            if ($cuota->wasRecentlyCreated) {
                $created++;
            }
        }
        $this->info('Cuotas creadas/actualizadas: ' . $created);
    }
}
