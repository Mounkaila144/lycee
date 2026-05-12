<?php

namespace Modules\PortailParent\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,
            'profession' => $this->profession,
            'address' => $this->address,
            'children_count' => $this->whenCounted('students'),
        ];
    }
}
