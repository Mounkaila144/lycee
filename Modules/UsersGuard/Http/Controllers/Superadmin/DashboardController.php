<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\Domain;
use Modules\UsersGuard\Entities\SuperAdmin;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use Throwable;

/**
 * Tableau de bord super-admin : statistiques globales (base centrale)
 * et chiffres de chaque école (lus dans la base de chaque tenant).
 */
class DashboardController extends Controller
{
    private const RECENT_TENANTS_LIMIT = 5;

    private const TEACHER_ROLE = 'Professeur';

    /**
     * GET /api/superadmin/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user() instanceof SuperAdmin) {
            return response()->json(['message' => 'Accès réservé au super-administrateur.'], 403);
        }

        $tenants = Tenant::query()->with('domains')->orderByDesc('created_at')->get();

        $schools = $tenants->map(fn (Tenant $tenant): array => $this->describeSchool($tenant));

        $activeCount = $schools->where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => [
                    'total' => $schools->count(),
                    'active' => $activeCount,
                    'inactive' => $schools->count() - $activeCount,
                ],
                'domains' => [
                    'total' => Domain::query()->count(),
                ],
                'totals' => [
                    'students' => (int) $schools->sum('students'),
                    'teachers' => (int) $schools->sum('teachers'),
                    'users' => (int) $schools->sum('users'),
                    'unavailable_schools' => $schools->where('available', false)->count(),
                ],
                'schools' => $schools->values(),
                'recent_tenants' => $schools->take(self::RECENT_TENANTS_LIMIT)
                    ->map(fn (array $school): array => collect($school)->only(['id', 'company_name', 'is_active', 'domains', 'created_at'])->all())
                    ->values(),
            ],
        ]);
    }

    /**
     * @return array{id: string, company_name: ?string, is_active: bool, domains: array<int, string>, created_at: ?string, database: string, available: bool, students: ?int, teachers: ?int, users: ?int, error: ?string}
     */
    private function describeSchool(Tenant $tenant): array
    {
        $databaseName = $tenant->database()->getName();

        return array_merge([
            'id' => (string) $tenant->id,
            'company_name' => $this->tenantValue($tenant, 'company_name'),
            'is_active' => $this->tenantValue($tenant, 'is_active') !== false,
            'domains' => $tenant->domains->pluck('domain')->values()->all(),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'database' => $databaseName,
        ], $this->countTenantRecords($tenant, $databaseName));
    }

    /**
     * Compte élèves, professeurs et utilisateurs dans la base du tenant.
     * Une base manquante ou illisible ne fait pas échouer le tableau de bord.
     *
     * @return array{available: bool, students: ?int, teachers: ?int, users: ?int, error: ?string}
     */
    private function countTenantRecords(Tenant $tenant, string $databaseName): array
    {
        $unavailable = fn (string $error): array => [
            'available' => false,
            'students' => null,
            'teachers' => null,
            'users' => null,
            'error' => $error,
        ];

        try {
            if (! $tenant->database()->manager()->databaseExists($databaseName)) {
                return $unavailable('Base de données introuvable.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return $unavailable('Impossible de vérifier la base de données.');
        }

        $originalTenant = tenant();

        try {
            tenancy()->initialize($tenant);

            $hasTable = fn (string $table): bool => Schema::connection('tenant')->hasTable($table);

            return [
                'available' => true,
                'students' => $hasTable('students') ? Student::query()->count() : null,
                'teachers' => $hasTable('users') && $hasTable('roles')
                    ? TenantUser::query()->whereHas('roles', fn ($query) => $query->where('name', self::TEACHER_ROLE))->count()
                    : null,
                'users' => $hasTable('users') ? TenantUser::query()->count() : null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $unavailable('Lecture de la base de données impossible.');
        } finally {
            if ($originalTenant) {
                tenancy()->initialize($originalTenant);
            } else {
                tenancy()->end();
            }
        }
    }

    /**
     * Lit un attribut du tenant, qu'il soit exposé en colonne virtuelle ou stocké dans "data".
     */
    private function tenantValue(Tenant $tenant, string $key): mixed
    {
        return $tenant->getAttribute($key) ?? data_get($tenant->getAttribute('data'), $key);
    }
}
