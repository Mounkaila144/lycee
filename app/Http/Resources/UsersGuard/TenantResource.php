<?php

namespace App\Http\Resources\UsersGuard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->resource->getAttribute('company_name'),
            'company_email' => $this->resource->getAttribute('company_email'),
            'company_phone' => $this->resource->getAttribute('company_phone'),
            'company_address' => $this->resource->getAttribute('company_address'),
            'company_logo' => $this->resource->getAttribute('company_logo'),
            'is_active' => $this->resource->isActive(),
            'is_uptodate' => $this->databaseExists(),
            'settings' => $this->resource->getAttribute('settings'),
            'trial_ends_at' => $this->resource->getAttribute('trial_ends_at'),
            'subscription_ends_at' => $this->resource->getAttribute('subscription_ends_at'),
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Whether the school database has been provisioned (used by the "Activer le site" action).
     */
    private function databaseExists(): bool
    {
        try {
            return $this->resource->database()->manager()->databaseExists($this->resource->database()->getName());
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
