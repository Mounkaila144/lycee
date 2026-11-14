<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres communs des rapports / du suivi des présences :
 * semestre (obligatoire), classe et période (facultatives).
 */
class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', Rule::exists('tenant.semesters', 'id')],
            'classe_id' => ['nullable', 'integer', Rule::exists('tenant.classes', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_absence_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'type' => [$this->routeIs('attendance.reports.export') ? 'required' : 'nullable', Rule::in(['students', 'modules'])],
            'status' => ['nullable', Rule::in(['active', 'acknowledged', 'all'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'date_to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'type.required' => 'Le type d\'export est obligatoire.',
            'type.in' => 'Le type d\'export doit être « students » ou « modules ».',
        ];
    }

    /**
     * Filtres de séances (classe / période) au format attendu par AttendanceReportService.
     *
     * @return array{classe_id: int|null, date_from: string|null, date_to: string|null}
     */
    public function sessionFilters(): array
    {
        return [
            'classe_id' => $this->filled('classe_id') ? $this->integer('classe_id') : null,
            'date_from' => $this->filled('date_from') ? $this->date('date_from')?->toDateString() : null,
            'date_to' => $this->filled('date_to') ? $this->date('date_to')?->toDateString() : null,
        ];
    }
}
