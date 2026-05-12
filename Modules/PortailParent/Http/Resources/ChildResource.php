<?php

namespace Modules\PortailParent\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche enfant exposée aux Parents (Stories Parent 01-02).
 *
 * Filtre les champs sensibles : pas de health_notes/blood_group exposés
 * via le portail Parent (RGPD mineurs — cf. security.md).
 */
class ChildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'sex' => $this->sex,
            'nationality' => $this->nationality,
            'photo_url' => $this->photo_url,
            'status' => $this->status,
            'pivot' => $this->whenPivotLoaded('parent_student', fn () => [
                'is_primary_contact' => (bool) $this->pivot->is_primary_contact,
                'is_financial_responsible' => (bool) $this->pivot->is_financial_responsible,
            ]),
        ];
    }
}
