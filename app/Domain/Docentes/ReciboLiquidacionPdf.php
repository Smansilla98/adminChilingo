<?php

namespace App\Domain\Docentes;

use App\Models\LiquidacionDocenteMensual;
use App\Support\PdfRender;

/**
 * Recibo de liquidación docente en PDF. Usa la misma vista y los mismos datos que el recibo HTML
 * (web y API), así que los montos son los que ya calcula LiquidacionService; no se recalcula nada.
 */
class ReciboLiquidacionPdf
{
    public static function pdf(LiquidacionDocenteMensual $liquidacion): string
    {
        $liquidacion->loadMissing(['profesor', 'aprobadaPor', 'pagadaPor']);

        return PdfRender::desdeVista('liquidaciones.recibo', [
            'l' => $liquidacion,
            'imprimir' => false,
        ], 'Recibo '.$liquidacion->numeroRecibo().' - Liquidación docente '.$liquidacion->periodo());
    }

    public static function nombreArchivo(LiquidacionDocenteMensual $liquidacion): string
    {
        return 'recibo-'.strtolower($liquidacion->numeroRecibo()).'.pdf';
    }
}
