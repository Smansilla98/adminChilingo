<?php

namespace App\Domain\Finanzas;

use App\Models\Pago;
use App\Support\PdfRender;

/**
 * Recibo de un pago de cuotas en PDF (web y API). Muestra lo que ve el alumno y la administración
 * de ese pago: alumnos, cuotas, montos, total, fecha y notas. No muestra la liquidación docente
 * (es un dato interno de la escuela).
 */
class ReciboPago
{
    /** Número visible del recibo: R-000123 (el id del pago, con ceros). */
    public static function numero(Pago $pago): string
    {
        return 'R-'.str_pad((string) $pago->id, 6, '0', STR_PAD_LEFT);
    }

    public static function pdf(Pago $pago): string
    {
        $pago->loadMissing(['detalles.alumno:id,nombre_apellido', 'detalles.cuota:id,nombre,monto,mes,año', 'registradoPor:id,name']);
        $numero = self::numero($pago);

        return PdfRender::desdeVista('pdf.recibo-pago', [
            'pago' => $pago,
            'numero' => $numero,
            'anulado' => $pago->estaAnulado(),
        ], 'Recibo '.$numero.' - Pago del '.$pago->fecha_pago->format('d/m/Y'));
    }

    public static function nombreArchivo(Pago $pago): string
    {
        return 'recibo-'.strtolower(self::numero($pago)).'.pdf';
    }
}
