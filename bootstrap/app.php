<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

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
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('whatsapp:resumen-admin')
            ->weeklyOn(1, '09:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('mail:resumen-admin')
            ->weeklyOn(1, '09:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->onOneServer();
    })->create();
