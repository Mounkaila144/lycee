<?php

namespace Modules\Enrollment\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Observers\StudentObserver;

class EnrollmentServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Enrollment';

    protected string $moduleNameLower = 'enrollment';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrations();
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->registerObservers();
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        Student::observe(StudentObserver::class);
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
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach ($this->app['config']->get('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
