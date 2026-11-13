<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera PDF desde una vista Blade con Dompdf (PHP puro: no necesita navegador).
 * Sin recursos remotos: el logo y la tipografía salen del propio repositorio.
 */
class PdfRender
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeVista(string $vista, array $datos, string $titulo): string
    {
        $html = view($vista, $datos)->render();

        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->setChroot([base_path(), public_path()]);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        // Metadatos en texto plano: el título (con el número de recibo) queda legible en el PDF.
        $dompdf->addInfo('Title', $titulo);
        $dompdf->render();

        return $dompdf->output();
    }
}
