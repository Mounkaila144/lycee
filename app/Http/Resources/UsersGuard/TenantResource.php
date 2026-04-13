<?php

namespace App\Http\Resources\UsersGuard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'company_name' => $data['company_name'] ?? null,
            'company_email' => $data['company_email'] ?? null,
            'company_phone' => $data['company_phone'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'company_logo' => $data['company_logo'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'settings' => $data['settings'] ?? null,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
