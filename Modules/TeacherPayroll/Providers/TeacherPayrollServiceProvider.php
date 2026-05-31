<?php

namespace Modules\TeacherPayroll\Providers;

use Illuminate\Support\ServiceProvider;

class TeacherPayrollServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Les migrations tenant sont gérées par stancl/tenancy via config/tenancy.php
        // (auto-découverte de Modules/*/Database/Migrations/tenant).
        $this->mergeConfigFrom(module_path('TeacherPayroll', 'Config/config.php'), 'teacherpayroll');
        $this->loadViewsFrom(module_path('TeacherPayroll', 'Resources/views'), 'teacherpayroll');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
