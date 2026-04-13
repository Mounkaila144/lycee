<?php

namespace Modules\Payroll\Providers;

use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Les migrations tenant sont gérées par stancl/tenancy via config/tenancy.php
        // Ne pas utiliser loadMigrationsFrom() pour les migrations tenant
        $this->mergeConfigFrom(module_path('Payroll', 'Config/config.php'), 'payroll');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
