<?php

namespace App\Domain\Finanzas;

use App\Models\ComprobanteCuotaAlumno;
use App\Support\PdfRender;

/**
 * Comprobante de pago de cuota (lo que envió o generó el alumno) en PDF, para web y API.
 * Muestra alumno, cuotas, monto, fecha, estado y referencia. No muestra notas internas.
 */
class ComprobanteCuotaPdf
{
    public static function numero(ComprobanteCuotaAlumno $c): string
    {
        return 'CC-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT);
    }

    public static function pdf(ComprobanteCuotaAlumno $c): string
    {
        $c->loadMissing(['alumno:id,nombre_apellido,dni', 'sede:id,nombre', 'items.bloque:id,nombre', 'items.cuota:id,nombre,monto,mes,año', 'pago:id']);
        $numero = self::numero($c);

        return PdfRender::desdeVista('pdf.comprobante-cuota', [
            'c' => $c,
            'numero' => $numero,
        ], 'Comprobante '.$numero.' - Cuota de '.$c->alumno->nombre_apellido);
    }

    public static function nombreArchivo(ComprobanteCuotaAlumno $c): string
    {
        return 'comprobante-cuota-'.strtolower(self::numero($c)).'.pdf';
    }
}
