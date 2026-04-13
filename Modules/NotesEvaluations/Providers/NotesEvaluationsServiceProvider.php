<?php

namespace Modules\NotesEvaluations\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\RetakeGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Observers\GradeObserver;
use Modules\NotesEvaluations\Observers\ModuleGradeObserver;
use Modules\NotesEvaluations\Observers\RetakeGradeObserver;
use Modules\NotesEvaluations\Observers\SemesterResultObserver;

class NotesEvaluationsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'NotesEvaluations';

    protected string $moduleNameLower = 'notesevaluations';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrations();
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/teacher.php'));
        $this->registerObservers();
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        Grade::observe(GradeObserver::class);
        ModuleGrade::observe(ModuleGradeObserver::class);
        SemesterResult::observe(SemesterResultObserver::class);
        RetakeGrade::observe(RetakeGradeObserver::class);
    }

    /**
     * Load migrations.
     */
    protected function loadMigrations(): void
    {
        // Migrations tenant gérées par stancl/tenancy
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        //
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
