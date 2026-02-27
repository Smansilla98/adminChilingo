<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WhatsAppRecordatoriosCommand extends Command
{
    protected $signature = 'whatsapp:recordatorios
                            {--cuotas : Enviar recordatorio de cuotas impagas}
                            {--eventos : Enviar aviso de próximos eventos (7 días)}
                            {--dias=7 : Días hacia adelante para eventos}
                            {--dry-run : Solo mostrar a quién se enviaría, sin enviar}';

    protected $description = 'Envía recordatorios por WhatsApp: cuotas impagas y/o próximos eventos';

    public function handle(WhatsAppService $whatsapp): int
    {
        if (!$whatsapp->isConfigured()) {
            $this->error('WhatsApp no configurado. Ver .env (TWILIO_*).');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $sendCuotas = $this->option('cuotas');
        $sendEventos = $this->option('eventos');

        if (!$sendCuotas && !$sendEventos) {
            $this->warn('Indicá al menos uno: --cuotas y/o --eventos');
            $this->line('Ejemplo: php artisan whatsapp:recordatorios --cuotas --eventos');
            return self::FAILURE;
        }

        $enviados = 0;
        $errores = 0;

        if ($sendCuotas) {
            [$ok, $err] = $this->enviarRecordatorioCuotas($whatsapp, $dryRun);
            $enviados += $ok;
            $errores += $err;
        }

        if ($sendEventos) {
            $dias = (int) $this->option('dias');
            [$ok, $err] = $this->enviarAvisoEventos($whatsapp, $dias, $dryRun);
            $enviados += $ok;
            $errores += $err;
        }

        $this->info("Enviados: {$enviados}, Errores: {$errores}");
        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Alumnos que no tienen pago registrado para la cuota activa del mes/año actual.
     */
    private function enviarRecordatorioCuotas(WhatsAppService $whatsapp, bool $dryRun): array
    {
        $cuotaActiva = Cuota::where('activo', true)
            ->where('año', (int) date('Y'))
            ->where('mes', (int) date('n'))
            ->first();

        if (!$cuotaActiva) {
            $this->warn('No hay cuota activa para el mes actual.');
            return [0, 0];
        }

        $pagadosIds = $cuotaActiva->pagoDetalles()->pluck('alumno_id')->unique();
        $alumnosImpagos = Alumno::where('activo', true)
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->whereNotIn('id', $pagadosIds)
            ->get();

        $this->info('Recordatorio cuota: ' . $alumnosImpagos->count() . ' alumnos sin pago registrado.');

        $enviados = 0;
        $errores = 0;
        $mensaje = "La Chilinga - Recordatorio: la cuota de {$cuotaActiva->nombre} (\$" . number_format($cuotaActiva->monto, 0, ',', '.') . ") sigue pendiente. Cualquier duda contactanos.";

        foreach ($alumnosImpagos as $alumno) {
            if ($dryRun) {
                $this->line("  [dry-run] {$alumno->nombre} - {$alumno->telefono}");
                $enviados++;
                continue;
            }
            $result = $whatsapp->send($mensaje, $alumno->telefono);
            if ($result['success']) {
                $enviados++;
            } else {
                $errores++;
                $this->warn("  {$alumno->nombre}: " . ($result['error'] ?? ''));
            }
        }

        return [$enviados, $errores];
    }

    /**
     * Eventos en los próximos N días; notifica a alumnos con teléfono (muestra simplificada).
     */
    private function enviarAvisoEventos(WhatsAppService $whatsapp, int $dias, bool $dryRun): array
    {
        $desde = Carbon::today();
        $hasta = Carbon::today()->addDays($dias);
        $eventos = Evento::whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')
            ->get();

        if ($eventos->isEmpty()) {
            $this->warn("No hay eventos en los próximos {$dias} días.");
            return [0, 0];
        }

        $lista = $eventos->map(fn ($e) => $e->fecha->format('d/m') . ' - ' . $e->titulo)->implode("\n");
        $mensaje = "La Chilinga - Próximos eventos:\n" . $lista . "\n¡Te esperamos!";

        $alumnos = Alumno::where('activo', true)
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->get();

        $this->info('Aviso eventos: ' . $alumnos->count() . ' alumnos con teléfono.');

        $enviados = 0;
        $errores = 0;
        foreach ($alumnos as $alumno) {
            if ($dryRun) {
                $this->line("  [dry-run] {$alumno->nombre} - {$alumno->telefono}");
                $enviados++;
                continue;
            }
            $result = $whatsapp->send($mensaje, $alumno->telefono);
            if ($result['success']) {
                $enviados++;
            } else {
                $errores++;
                $this->warn("  {$alumno->nombre}: " . ($result['error'] ?? ''));
            }
        }

        return [$enviados, $errores];
    }
}
