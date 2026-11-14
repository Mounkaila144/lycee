<?php

namespace Modules\Settings\Providers;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Settings';

    public function boot(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), 'settings');
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), 'settings');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
