<?php

namespace Modules\Documents\Providers;

use Illuminate\Support\ServiceProvider;

class DocumentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Les migrations tenant sont gérées par stancl/tenancy via config/tenancy.php
        // Ne pas utiliser loadMigrationsFrom() pour les migrations tenant
        $this->mergeConfigFrom(module_path('Documents', 'Config/config.php'), 'documents');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
