<?php

namespace App\Providers;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\ResolvedorAcceso;
use App\Domain\Notificaciones\Avisos;
use App\Domain\Personas\PersonaService;
use App\Models\Alumno;
use App\Models\Diseno;
use App\Models\PagoDetalle;
use App\Models\Persona;
use App\Models\Profesor;
use App\Models\User;
use App\Policies\DisenoPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ResolvedorAcceso::class);
        $this->app->singleton(PersonaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Diseno::class, DisenoPolicy::class);

        // Permisos granulares con alcance: `can('pagos.create')` responde con los permisos
        // efectivos; con un modelo (`can('update', $alumno)`) decide la Policy.
        Gate::before(function (User $user, string $ability, array $arguments) {
            $acceso = $user->acceso();
            if ($acceso->esSuperadmin()) {
                return true;
            }
            if ($arguments === [] && CatalogoPermisos::existe($ability)) {
                return $acceso->puede($ability);
            }

            return null;
        });

        $this->registrarSincronizacionDePersonas();
        $this->registrarLimitesDeUso();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Toda ficha nueva (alumno, profesor, cuenta) queda vinculada a una Persona, sin
     * importar desde dónde se cree (CRUD, importación, alta rápida, API).
     */
    private function registrarSincronizacionDePersonas(): void
    {
        $personas = fn () => $this->app->make(PersonaService::class);

        Alumno::created(fn (Alumno $a) => $personas()->asegurarParaAlumno($a));
        Alumno::updated(fn (Alumno $a) => $personas()->sincronizarDesdeAlumno($a));
        Profesor::created(fn (Profesor $p) => $personas()->asegurarParaProfesor($p));
        Profesor::updated(fn (Profesor $p) => $personas()->sincronizarDesdeProfesor($p));
        User::created(fn (User $u) => $personas()->asegurarParaUsuario($u));
        // Aviso al alumno cuando se registra un pago a su nombre (una sola vez por pago/cuota).
        PagoDetalle::created(function (PagoDetalle $d) {
            rescue(fn () => $this->app->make(Avisos::class)->pagoRegistrado($d), report: true);
        });
        Persona::updated(function (Persona $p) use ($personas) {
            if ($p->wasChanged(['nombre', 'apellido', 'dni', 'fecha_nacimiento', 'telefono', 'email'])) {
                $personas()->propagar($p);
            }
        });
    }

    private function registrarLimitesDeUso(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $usuario = mb_strtolower((string) $request->input('username'));

            return [
                Limit::perMinute(5)->by('login:'.$usuario.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
