<?php

namespace App\Console\Commands;

use App\Domain\Datos\Diagnostico;
use Illuminate\Console\Command;

class ChilingaDiagnoseCommand extends Command
{
    protected $signature = 'chilinga:diagnose
                            {--json : Salida en JSON}
                            {--strict : Termina con código 1 si hay hallazgos de severidad alta}';

    protected $description = 'Diagnostica duplicados y datos inconsistentes (personas, alumnos, profesores, usuarios, sedes, bloques, cuotas, pagos). Solo lectura.';

    public function handle(Diagnostico $diagnostico): int
    {
        $resultado = $diagnostico->ejecutar();

        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($resultado as $chequeo) {
                $n = count($chequeo['items']);
                $marca = $n === 0 ? '<fg=green>✓</>' : ($chequeo['severidad'] === 'alta' ? '<fg=red>✗</>' : '<fg=yellow>!</>');
                $this->line("$marca {$chequeo['titulo']}: ".($n === 0 ? 'sin hallazgos' : "$n hallazgo(s)"));
                if ($n > 0) {
                    $filas = array_map(fn ($i) => array_map(fn ($v) => is_array($v) ? implode(',', $v) : (string) $v, $i), array_slice($chequeo['items'], 0, 25));
                    $this->table(array_keys($filas[0]), $filas);
                    if ($n > 25) {
                        $this->line('  … y '.($n - 25).' más (usá --json para verlos todos).');
                    }
                }
            }
        }

        $graves = collect($resultado)->filter(fn ($c) => $c['severidad'] === 'alta' && $c['items'] !== [])->count();

        return $this->option('strict') && $graves > 0 ? self::FAILURE : self::SUCCESS;
    }
}
