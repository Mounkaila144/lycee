<?php

namespace App\Http\Resources\UsersGuard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleNames = $this->getRoleNames()->toArray();
        $primaryRole = $this->resolvePrimaryRole($roleNames);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'application' => $this->application,
            'is_active' => $this->is_active,
            'sex' => $this->sex,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'lastlogin' => $this->lastlogin,
            'roles' => $roleNames,
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'primary_role' => $primaryRole,
            'home_route' => $this->resolveHomeRoute($primaryRole),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Resolve the primary role of the user based on configured hierarchy.
     *
     * @param  array<int, string>  $roleNames
     */
    private function resolvePrimaryRole(array $roleNames): ?string
    {
        $hierarchy = config('role-routes.hierarchy', []);

        foreach ($hierarchy as $candidate) {
            if (in_array($candidate, $roleNames, true)) {
                return $candidate;
            }
        }

        return $roleNames[0] ?? null;
    }

    /**
     * Resolve the frontend home route based on the primary role.
     */
    private function resolveHomeRoute(?string $primaryRole): string
    {
        $routes = config('role-routes.home_routes', []);
        $default = config('role-routes.default_home', '/admin/profile');

        return $routes[$primaryRole] ?? $default;
    }
}
