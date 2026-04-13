<?php

namespace Modules\Exams\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\Exams\Http\Controllers';

    public function map(): void
    {
        $this->mapAdminRoutes();
        $this->mapWebRoutes();
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware(['api'])
            ->namespace($this->namespace.'\\Admin')
            ->group(module_path('Exams', '/Routes/admin.php'));
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(module_path('Exams', '/Routes/web.php'));
    }
}
