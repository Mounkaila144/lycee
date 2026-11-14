<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsersGuard\StoreTenantRequest;
use App\Http\Requests\UsersGuard\UpdateTenantRequest;
use App\Http\Resources\UsersGuard\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\UsersGuard\Entities\Tenant;
use Throwable;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = Tenant::query()->with('domains');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('data->company_name', 'like', "%{$search}%")
                    ->orWhere('data->company_email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->latest()->paginate($perPage);

        return TenantResource::collection($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            $tenant = Tenant::create([
                'id' => $data['id'],
                'company_name' => $data['company_name'],
                'company_email' => $data['company_email'] ?? null,
                'company_phone' => $data['company_phone'] ?? null,
                'company_address' => $data['company_address'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if (isset($data['domains']) && is_array($data['domains'])) {
                foreach ($data['domains'] as $index => $domainData) {
                    $tenant->domains()->create([
                        'domain' => $domainData['domain'],
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Tenant créé avec succès.',
                'tenant' => new TenantResource($tenant->load('domains')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $tenant = Tenant::findOrFail($id);
            $data = $request->validated();

            foreach (['company_name', 'company_email', 'company_phone', 'company_address'] as $field) {
                if (array_key_exists($field, $data)) {
                    $tenant->setAttribute($field, $data[$field]);
                }
            }

            if (isset($data['is_active'])) {
                $tenant->setAttribute('is_active', (bool) $data['is_active']);
            }

            $tenant->save();

            if (isset($data['domains']) && is_array($data['domains'])) {
                $tenant->domains()->delete();

                foreach ($data['domains'] as $index => $domainData) {
                    $tenant->domains()->create([
                        'domain' => $domainData['domain'],
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Tenant modifié avec succès.',
                'tenant' => new TenantResource($tenant->load('domains')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $tenant = Tenant::findOrFail($id);

            $tenant->domains()->delete();
            $tenant->delete();

            DB::commit();

            return response()->json([
                'message' => 'Tenant supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle tenant active status.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $isActive = ! $tenant->isActive();
        $tenant->setAttribute('is_active', $isActive);
        $tenant->save();

        return response()->json([
            'message' => $isActive ? 'Tenant activé avec succès.' : 'Tenant désactivé avec succès.',
            'tenant' => new TenantResource($tenant->load('domains')),
        ]);
    }

    /**
     * Activate a site: provision its database (create + tenant migrations) when it
     * does not exist yet, then mark the school as active.
     *
     * An already provisioned database is never migrated from here: migrations of a
     * live school follow the deployment procedure (backup first).
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $databaseName = $tenant->database()->getName();
        $manager = $tenant->database()->manager();
        $output = null;

        if ($manager->databaseExists($databaseName)) {
            $message = 'Base de données déjà présente : aucune migration exécutée. Site marqué actif.';
        } else {
            set_time_limit(600);

            try {
                $manager->createDatabase($tenant);

                Artisan::call('tenants:migrate', [
                    '--tenants' => [$tenant->getTenantKey()],
                    '--force' => true,
                ]);
                $output = Artisan::output();
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => 'Échec de la création ou de la migration de la base '.$databaseName.' : '.$exception->getMessage(),
                ], 500);
            }

            $message = 'Base de données '.$databaseName.' créée et migrée. Site activé.';
        }

        $tenant->setAttribute('is_active', true);
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new TenantResource($tenant->load('domains')),
            'output' => $output,
        ]);
    }

    /**
     * Add a domain to a tenant.
     */
    public function addDomain(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'unique:domains,domain'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $tenant = Tenant::findOrFail($id);

        if ($request->boolean('is_primary')) {
            $tenant->domains()->update(['is_primary' => false]);
        }

        $domain = $tenant->domains()->create([
            'domain' => $request->input('domain'),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return response()->json([
            'message' => 'Domaine ajouté avec succès.',
            'tenant' => new TenantResource($tenant->load('domains')),
        ], 201);
    }

    /**
     * Remove a domain from a tenant.
     */
    public function removeDomain(string $tenantId, string $domainId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $domain = $tenant->domains()->findOrFail($domainId);

        if ($domain->is_primary && $tenant->domains()->count() > 1) {
            return response()->json([
                'message' => 'Impossible de supprimer le domaine principal. Veuillez d\'abord définir un autre domaine comme principal.',
            ], 422);
        }

        $domain->delete();

        return response()->json([
            'message' => 'Domaine supprimé avec succès.',
            'tenant' => new TenantResource($tenant->load('domains')),
        ]);
    }
}
