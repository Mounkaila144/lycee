<?php

namespace Modules\Documents\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\Documents\Http\Controllers';

    public function map(): void
    {
        Route::middleware(['api'])->namespace($this->namespace.'\Admin')
            ->group(module_path('Documents', '/Routes/admin.php'));
    }
}
