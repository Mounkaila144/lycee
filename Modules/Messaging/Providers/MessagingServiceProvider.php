<?php

namespace Modules\Messaging\Providers;

use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Messaging';

    public function boot(): void
    {
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
