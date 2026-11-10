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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'profesor_o_admin' => \App\Http\Middleware\EnsureProfesorOrAdmin::class,
            'modulo' => \App\Http\Middleware\CheckModuloAccess::class,
            'permiso' => \App\Http\Middleware\CheckPermiso::class,
            'activo' => \App\Http\Middleware\EnsureUsuarioActivo::class,
            'clave' => \App\Http\Middleware\EnsureClaveCambiada::class,
            'material' => \App\Http\Middleware\AccesoMaterialToque::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Web: sesión. API: se aplica después de auth:sanctum (routes/api_v1.php).
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUsuarioActivo::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureClaveCambiada::class);
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/whatsapp-status',
            'webhooks/mercadopago',
        ]);
        // Detrás de Railway/HTTPS: confiar en proxies para URL y esquema correctos
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry: solo reporta si SENTRY_LARAVEL_DSN está seteado (sin datos personales, ver config/sentry.php).
        \Sentry\Laravel\Integration::handles($exceptions);

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

        // Avisos de la app (idempotentes: no se duplican aunque corran de nuevo).
        // Cuota nueva: cada 15 minutos (no hay cola; así no frena la pantalla al crear la cuota).
        $schedule->command('chilinga:avisos cuotas-nuevas')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos cuotas-por-vencer')->dailyAt('11:05')->timezone(config('app.timezone'))->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos cuotas-vencidas')->dailyAt('11:00')->timezone(config('app.timezone'))->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos ausencias')->dailyAt('10:00')->timezone(config('app.timezone'))->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos eventos')->dailyAt('18:00')->timezone(config('app.timezone'))->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos eventos-nuevos')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('chilinga:avisos pasar-lista')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

        // Pagos de Mercado Pago cuyo aviso no llegó: se buscan por referencia y se registran.
        $schedule->command('chilinga:mercadopago:conciliar')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();

        // Backup diario de la base en el disco persistente (se guardan los últimos 14).
        // Con RESPALDO_DISCO_EXTERNO + RESPALDO_CLAVE sube además una copia cifrada (S3/R2). Si sale bien, avisa a HEARTBEAT_URL_RESPALDO.
        $schedule->command('chilinga:respaldo')->dailyAt('03:30')->timezone(config('app.timezone'))->withoutOverlapping()->onOneServer()
            ->pingOnSuccessIf(filled(config('operacion.heartbeat.respaldo')), (string) config('operacion.heartbeat.respaldo'));

        // Latido del scheduler: si HEARTBEAT_URL_SCHEDULER deja de recibir pings, el scheduler está caído.
        if (filled(config('operacion.heartbeat.scheduler'))) {
            $schedule->call(fn () => null)->name('latido-scheduler')
                ->cron('*/'.max(1, min(59, (int) config('operacion.heartbeat.scheduler_minutos', 10))).' * * * *')
                ->pingOnSuccess((string) config('operacion.heartbeat.scheduler'));
        }

        $schedule->command('mail:resumen-admin')
            ->weekdays()
            ->at('10:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->onOneServer();
    })->create();
