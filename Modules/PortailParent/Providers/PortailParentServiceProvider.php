<?php

namespace Modules\PortailParent\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Policies\ChildPolicy;

class PortailParentServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'PortailParent';

    protected string $moduleNameLower = 'portailparent';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->registerPolicies();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Student::class, ChildPolicy::class);
    }

    public function provides(): array
    {
        return [];
    }
}
