<?php

namespace App\Console\Commands;

use App\Domain\Personas\PersonaService;
use App\Models\Persona;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PersonasFusionarCommand extends Command
{
    protected $signature = 'chilinga:personas:fusionar {conservar : ID de la persona que queda} {duplicada : ID de la persona que se absorbe} {--force : No pedir confirmación}';

    protected $description = 'Fusiona dos personas duplicadas: mueve fichas de alumno/docente, cuenta y asignaciones. La duplicada queda dada de baja (no se borra).';

    public function handle(PersonaService $personas): int
    {
        $conservar = Persona::query()->findOrFail((int) $this->argument('conservar'));
        $duplicada = Persona::query()->findOrFail((int) $this->argument('duplicada'));

        $this->line("Queda:     #{$conservar->id} {$conservar->nombre_completo} (DNI {$conservar->dni})");
        $this->line("Se absorbe: #{$duplicada->id} {$duplicada->nombre_completo} (DNI {$duplicada->dni})");
        if (! $this->option('force') && ! $this->confirm('¿Confirmás la fusión?')) {
            return self::FAILURE;
        }

        try {
            $personas->fusionar($conservar, $duplicada);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->join(' '));

            return self::FAILURE;
        }
        $this->info('Personas fusionadas.');

        return self::SUCCESS;
    }
}
