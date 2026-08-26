<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'profesor_o_admin' => \App\Http\Middleware\EnsureProfesorOrAdmin::class,
            'modulo' => \App\Http\Middleware\CheckModuloAccess::class,
        ]);
        // Detrás de Railway/HTTPS: confiar en proxies para URL y esquema correctos
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'El archivo supera el límite de 100 MB.',
                ], 413);
            }

            return redirect()
                ->back()
                ->withInput($request->except('archivo'))
                ->withErrors([
                    'archivo' => 'El archivo supera el límite de 100 MB. Comprimí el video o pegá un enlace.',
                ]);
        });

        $exceptions->respond(function (Response $response, \Throwable $e, $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'La sesión expiró. Recargá la página e intentá de nuevo.',
                ], 419);
            }

            $mensaje = 'La sesión expiró. Ingresá de nuevo y repetí la acción.';
            $except = [
                'password',
                'password_confirmation',
                'login_password',
                'login_password_confirmation',
                'current_password',
                '_token',
            ];

            if ($request->user()) {
                return redirect()
                    ->back()
                    ->withInput($request->except($except))
                    ->with('error', 'La página venció. Revisá los datos y volvé a guardar.');
            }

            return redirect()
                ->guest(route('login'))
                ->with('error', $mensaje);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('whatsapp:resumen-admin')
            ->weeklyOn(1, '09:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('mail:resumen-admin')
            ->weekdays()
            ->at('10:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->onOneServer();
    })->create();
