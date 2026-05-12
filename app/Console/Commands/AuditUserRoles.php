<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use Spatie\Permission\Models\Role;

class AuditUserRoles extends Command
{
    protected $signature = 'users:audit-roles {--tenant= : Specific tenant id to audit}';

    protected $description = 'Audit role/permission consistency across tenants';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenant found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("=== Tenant: {$tenant->id} ({$tenant->company_name}) ===");

            try {
                tenancy()->initialize($tenant);

                $roles = Role::query()->withCount('users')->get();
                if ($roles->isEmpty()) {
                    $this->warn('  No roles defined.');
                } else {
                    $this->line(sprintf('  %-20s %-10s %s', 'Role', 'Guard', 'Users'));
                    foreach ($roles as $role) {
                        $this->line(sprintf('  %-20s %-10s %d', $role->name, $role->guard_name, $role->users_count));
                    }
                }

                $totalUsers = TenantUser::query()->count();
                $orphans = TenantUser::query()->doesntHave('roles')->count();
                $multiple = TenantUser::query()->has('roles', '>', 1)->count();

                $this->line('');
                $this->line("  Total users:           {$totalUsers}");
                $this->line("  Users without role:    {$orphans}");
                $this->line("  Users with >1 roles:   {$multiple}");

                $byApp = TenantUser::query()
                    ->selectRaw('application, COUNT(*) as count')
                    ->groupBy('application')
                    ->pluck('count', 'application');
                $this->line('  Distribution by application:');
                foreach ($byApp as $app => $count) {
                    $this->line("    {$app}: {$count}");
                }

                tenancy()->end();
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
                tenancy()->end();
            }

            $this->line('');
        }

        return self::SUCCESS;
    }
}
