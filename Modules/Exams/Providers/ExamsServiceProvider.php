<?php

namespace Modules\Exams\Providers;

use Illuminate\Support\ServiceProvider;

class ExamsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Exams';

    protected string $moduleNameLower = 'exams';

    public function boot(): void
    {
        $this->registerConfig();
        // Les migrations tenant sont gérées par stancl/tenancy via config/tenancy.php
        // Ne pas utiliser loadMigrationsFrom() pour les migrations tenant
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }
}
