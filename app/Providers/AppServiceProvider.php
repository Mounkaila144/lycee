<?php

namespace App\Providers;

use App\Http\Middleware\InitializeActiveTenancyByDomain;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Identification par domaine + blocage des écoles désactivées par le super-admin.
        $this->app->bind(InitializeTenancyByDomain::class, InitializeActiveTenancyByDomain::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
