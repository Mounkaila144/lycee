<?php

namespace Modules\TeacherPayroll\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\TeacherPayroll\Http\Controllers';

    public function map(): void
    {
        Route::prefix('api')->middleware(['api'])->namespace($this->namespace.'\Admin')
            ->group(module_path('TeacherPayroll', '/Routes/admin.php'));
    }
}
