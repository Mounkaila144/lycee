<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsersGuard\StoreTenantRequest;
use App\Http\Requests\UsersGuard\UpdateTenantRequest;
use App\Http\Resources\UsersGuard\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\UsersGuard\Entities\Tenant;

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
                    ->orWhereJsonContains('data->company_name', $search)
                    ->orWhereJsonContains('data->company_email', $search);
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
                'data' => [
                    'company_name' => $data['company_name'],
                    'company_email' => $data['company_email'] ?? null,
                    'company_phone' => $data['company_phone'] ?? null,
                    'company_address' => $data['company_address'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ],
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

            $tenantData = $tenant->data ?? [];

            if (isset($data['company_name'])) {
                $tenantData['company_name'] = $data['company_name'];
            }
            if (isset($data['company_email'])) {
                $tenantData['company_email'] = $data['company_email'];
            }
            if (isset($data['company_phone'])) {
                $tenantData['company_phone'] = $data['company_phone'];
            }
            if (isset($data['company_address'])) {
                $tenantData['company_address'] = $data['company_address'];
            }
            if (isset($data['is_active'])) {
                $tenantData['is_active'] = $data['is_active'];
            }

            $tenant->update(['data' => $tenantData]);

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

        $tenantData = $tenant->data ?? [];
        $isActive = ! ($tenantData['is_active'] ?? true);
        $tenantData['is_active'] = $isActive;

        $tenant->update(['data' => $tenantData]);

        return response()->json([
            'message' => $isActive ? 'Tenant activé avec succès.' : 'Tenant désactivé avec succès.',
            'tenant' => new TenantResource($tenant->load('domains')),
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
