<?php

namespace Modules\Payroll\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\Payroll\Http\Controllers';

    public function map(): void
    {
        Route::middleware(['api'])->namespace($this->namespace.'\Admin')
            ->group(module_path('Payroll', '/Routes/admin.php'));
    }
}
