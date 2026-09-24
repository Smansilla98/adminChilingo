<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Campana de avisos del panel web: usa las mismas notificaciones internas que
 * la app móvil (tabla notifications). Cada usuario solo toca las suyas.
 */
class NotificacionWebController extends Controller
{
    public function leer(Request $request, string $id): RedirectResponse
    {
        $aviso = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $aviso->markAsRead();

        $enlace = (string) ($aviso->data['enlace'] ?? '');

        return $this->esInterno($enlace) ? redirect()->to($enlace) : back();
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Avisos marcados como leídos.');
    }

    /** Solo se sigue un enlace relativo o del mismo sitio (evita redirecciones abiertas). */
    private function esInterno(string $enlace): bool
    {
        if ($enlace === '') {
            return false;
        }
        if (str_starts_with($enlace, '/') && ! str_starts_with($enlace, '//')) {
            return true;
        }

        return parse_url($enlace, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST);
    }
}
