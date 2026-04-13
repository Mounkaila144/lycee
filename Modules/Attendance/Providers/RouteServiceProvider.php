<?php

namespace Modules\Attendance\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\Attendance\Http\Controllers';

    public function map(): void
    {
        $this->mapAdminRoutes();
        $this->mapWebRoutes();
    }

    protected function mapAdminRoutes(): void
    {
        Route::middleware(['api'])
            ->namespace($this->namespace)
            ->group(module_path('Attendance', '/Routes/admin.php'));
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(module_path('Attendance', '/Routes/web.php'));
    }
}
