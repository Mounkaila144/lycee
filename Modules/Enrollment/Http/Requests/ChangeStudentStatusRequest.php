<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Services\StudentStatusService;

class ChangeStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(StudentStatusService::STATUSES),
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
            'effective_date' => [
                'sometimes',
                'date',
                'after_or_equal:today',
            ],
            'document' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Le nouveau statut est obligatoire',
            'status.in' => 'Le statut doit être: Actif, Suspendu, Exclu, Diplômé, Abandon ou Transféré',
            'reason.required' => 'Le motif du changement est obligatoire',
            'reason.min' => 'Le motif doit contenir au moins 10 caractères',
            'reason.max' => 'Le motif ne doit pas dépasser 1000 caractères',
            'effective_date.date' => 'La date effective doit être une date valide',
            'effective_date.after_or_equal' => 'La date effective doit être aujourd\'hui ou une date future',
            'document.file' => 'Le document doit être un fichier valide',
            'document.mimes' => 'Le document doit être au format PDF, JPG ou PNG',
            'document.max' => 'Le document ne doit pas dépasser 2 MB',
        ];
    }

    /**
     * Get the validated status.
     */
    public function getNewStatus(): string
    {
        return $this->validated()['status'];
    }

    /**
     * Get the reason for the status change.
     */
    public function getReason(): string
    {
        return $this->validated()['reason'];
    }

    /**
     * Get the effective date (defaults to today if not provided).
     */
    public function getEffectiveDate(): ?string
    {
        return $this->validated()['effective_date'] ?? null;
    }

    /**
     * Get the uploaded document if any.
     */
    public function getDocument(): ?\Illuminate\Http\UploadedFile
    {
        return $this->file('document');
    }
}
