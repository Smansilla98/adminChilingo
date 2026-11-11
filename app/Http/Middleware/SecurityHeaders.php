<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad básicas para el panel y la API.
 *
 * Content-Security-Policy en dos partes (ver docs/DEPLOY.md, CSP_ESTRICTA):
 * - Siempre se aplica una política mínima que no rompe nada: nadie puede meter la web en un
 *   iframe de otro sitio (frame-ancestors 'self'), no se cargan plugins (object-src 'none')
 *   y no se puede cambiar la base de las URL (base-uri 'self').
 * - La política completa (de dónde se cargan scripts, estilos, fuentes, videos…) va en modo
 *   Report-Only: el navegador avisa en la consola lo que bloquearía, sin bloquearlo. Las vistas
 *   tienen scripts y estilos inline, por eso lleva 'unsafe-inline'. Cuando la consola esté
 *   limpia, CSP_ESTRICTA=true la pasa a obligatoria.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Solo páginas HTML: un PDF o una imagen servidos con object-src 'none' pueden no verse
        // en el visor del navegador.
        $html = str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        if ($html && ! $response->headers->has('Content-Security-Policy')) {
            $estricta = (bool) config('chilinga.csp_estricta', false);
            $response->headers->set('Content-Security-Policy', $estricta ? self::politicaCompleta() : self::politicaMinima());
            // En local, Vite sirve los assets desde otro puerto: la completa solo se informa fuera de local.
            if (! $estricta && ! app()->environment('local')) {
                $response->headers->set('Content-Security-Policy-Report-Only', self::politicaCompleta());
            }
        }

        return $response;
    }

    public static function politicaMinima(): string
    {
        return "frame-ancestors 'self'; object-src 'none'; base-uri 'self'";
    }

    public static function politicaCompleta(): string
    {
        $directivas = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://fonts.bunny.net', 'https://cdn.jsdelivr.net'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', 'https://fonts.bunny.net', 'https://cdn.jsdelivr.net'],
            // Fotos, mapas (tiles de OpenStreetMap), QR y miniaturas de videos.
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'media-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", 'https://nominatim.openstreetmap.org', 'https://cdn.jsdelivr.net'],
            'frame-src' => ["'self'", 'blob:', 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'],
            'worker-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return implode('; ', array_map(fn ($k, $v) => $k.' '.implode(' ', $v), array_keys($directivas), $directivas));
    }
}
