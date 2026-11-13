<?php

namespace Modules\Documents\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Documents\Entities\Document;
use Modules\Documents\Policies\DocumentPolicy;

class DocumentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Les migrations tenant sont gérées par stancl/tenancy via config/tenancy.php
        // Ne pas utiliser loadMigrationsFrom() pour les migrations tenant
        $this->mergeConfigFrom(module_path('Documents', 'Config/config.php'), 'documents');

        Gate::policy(Document::class, DocumentPolicy::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
