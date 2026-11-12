<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Healthcheck de Railway (/salud): 200 si la app conecta a la base, 503 si no.
 * Controlador y no closure: route:cache en producción no admite closures.
 */
class SaludController extends Controller
{
    public function __invoke()
    {
        $db = true;
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $db = false;
        }

        return response()->json([
            'ok' => $db,
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ], $db ? 200 : 503);
    }
}
