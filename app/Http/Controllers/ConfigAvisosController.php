<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\CobroCuotasService;
use App\Domain\Notificaciones\Avisos;
use App\Domain\Notificaciones\ConfigAvisos;
use App\Domain\Notificaciones\NotificacionService;
use App\Models\Ajuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Configuración → Avisos y cobro: qué avisos automáticos salen, por qué canales, con qué texto, y los datos para pagar. */
class ConfigAvisosController extends Controller
{
    public const CANALES = ['interna' => 'Bandeja de la app y del panel', 'push' => 'Notificación en el celular', 'mail' => 'Mail', 'whatsapp' => 'WhatsApp'];

    public function edit(CobroCuotasService $cobros): View
    {
        return view('configuracion.avisos', [
            'avisos' => ConfigAvisos::todos(),
            'variables' => ConfigAvisos::VARIABLES,
            'canales' => self::CANALES,
            'cobro' => Ajuste::leer('cobro'),
            'cobroEfectivo' => $cobros->datosTransferencia() ?? [],
            'mercadoPago' => $cobros->mercadoPagoDisponible(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate(self::reglas());
        ConfigAvisos::guardar($datos['avisos'] ?? [], $request->user()->id);
        Ajuste::guardar('cobro', array_map(fn ($v) => trim((string) $v), array_filter($datos['cobro'] ?? [], fn ($v) => $v !== null)), $request->user()->id);

        return back()->with('success', 'Configuración de avisos y cobro guardada.');
    }

    /** Manda ahora los avisos de cuotas nuevas (sin esperar el próximo cuarto de hora). */
    public function enviarCuotasNuevas(Avisos $avisos): RedirectResponse
    {
        $n = $avisos->cuotasNuevas();

        return back()->with('success', $n > 0 ? "Se enviaron {$n} avisos de cuotas nuevas." : 'No había avisos de cuotas nuevas pendientes.');
    }

    /** Prueba: le manda a quien configura el aviso elegido, por los canales elegidos. */
    public function probar(Request $request, NotificacionService $notificaciones): RedirectResponse
    {
        $tipo = $request->validate(['tipo' => ['required', 'in:'.implode(',', array_keys(ConfigAvisos::defaults()))]])['tipo'];
        $config = ConfigAvisos::de($tipo);
        $r = $notificaciones->enviar($request->user(), "prueba:{$tipo}:".now()->timestamp, [
            'tipo' => $tipo,
            ...ConfigAvisos::texto($tipo, ['alumno' => $request->user()->name, 'cuota' => 'Cuota de ejemplo', 'monto' => '$25.000',
                'vencimiento' => now()->addDays(5)->format('d/m'), 'dias' => '5', 'alias' => app(CobroCuotasService::class)->datosTransferencia()['alias'] ?? 'tu.alias']),
            'enlace' => 'app://mi-cuenta',
        ], $config['canales']);

        $partes = collect($r)->map(fn ($estado, $canal) => (self::CANALES[$canal] ?? $canal).': '.$estado)->implode(' · ');

        return back()->with('success', 'Prueba enviada. '.$partes);
    }

    /** @return array<string, mixed> */
    public static function reglas(): array
    {
        return [
            'avisos' => ['nullable', 'array'],
            'avisos.*.activo' => ['nullable', 'boolean'],
            'avisos.*.canales' => ['nullable', 'array'],
            'avisos.*.canales.*' => ['nullable', 'string', 'in:,'.implode(',', NotificacionService::CANALES)],
            'avisos.*.titulo' => ['nullable', 'string', 'max:120'],
            'avisos.*.mensaje' => ['nullable', 'string', 'max:600'],
            'avisos.*.dias' => ['nullable', 'integer', 'min:1', 'max:60'],
            'cobro' => ['nullable', 'array'],
            'cobro.alias' => ['nullable', 'string', 'max:60'],
            'cobro.cbu' => ['nullable', 'string', 'max:30'],
            'cobro.titular' => ['nullable', 'string', 'max:120'],
            'cobro.cuit' => ['nullable', 'string', 'max:20'],
            'cobro.banco' => ['nullable', 'string', 'max:80'],
        ];
    }
}
