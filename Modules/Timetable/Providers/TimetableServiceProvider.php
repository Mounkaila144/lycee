<?php

namespace Modules\Timetable\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Observers\TimetableExceptionObserver;
use Modules\Timetable\Services\TimetableNotificationService;

class TimetableServiceProvider extends ServiceProvider
{
    protected $moduleName = 'Timetable';

    protected $moduleNameLower = 'timetable';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrations();
        $this->registerObservers();
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/frontend.php'));
    }

    /**
     * Register observers for automatic notifications.
     */
    protected function registerObservers(): void
    {
        TimetableException::observe(TimetableExceptionObserver::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register notification service as singleton
        $this->app->singleton(TimetableNotificationService::class, function ($app) {
            return new TimetableNotificationService();
        });
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
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Get publishable view paths.
     */
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

    /**
     * Load migrations.
     * Note: Les migrations tenant sont gérées par stancl/tenancy
     */
    protected function loadMigrations(): void
    {
        // Les migrations tenant sont exécutées par stancl/tenancy lors de la création d'un tenant
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
