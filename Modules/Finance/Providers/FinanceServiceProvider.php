<?php

namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Finance';

    protected string $moduleNameLower = 'finance';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrations();
    }

    /**
     * Load migrations.
     * Note: Les migrations tenant sont gérées par stancl/tenancy
     * et ne doivent pas être chargées directement ici.
     */
    protected function loadMigrations(): void
    {
        // Ne pas charger les migrations tenant directement
        // Elles seront exécutées par tenancy lors de la création d'un tenant
        // $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations/tenant'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
