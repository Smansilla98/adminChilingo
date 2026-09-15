<?php

namespace App\Providers;

use App\Models\Diseno;
use App\Policies\DisenoPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Diseno::class, DisenoPolicy::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
