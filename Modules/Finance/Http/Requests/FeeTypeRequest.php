<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\StructureAcademique\Entities\Level;

/**
 * Création / modification d'un type de frais.
 *
 * Les niveaux concernés (`levels`) et les montants par niveau (`level_amounts`)
 * sont stockés dans la colonne JSON `applies_to`.
 */
class FeeTypeRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const CATEGORIES = ['tuition', 'registration', 'exam', 'library', 'lab', 'sports', 'insurance', 'card', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isUpdate() ? 'sometimes' : 'required';
        $feeTypeId = $this->route('id');

        return [
            'code' => [$presence, 'string', 'max:50', Rule::unique('tenant.fee_types', 'code')->ignore($feeTypeId)],
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_amount' => [$presence, 'numeric', 'min:0'],
            'category' => [$presence, 'string', Rule::in(self::CATEGORIES)],
            'is_mandatory' => ['sometimes', 'boolean'],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('tenant.academic_years', 'id')],
            'applies_to' => ['nullable', 'array'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::exists('tenant.levels', 'code')],
            'level_amounts' => ['nullable', 'array'],
            'level_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $levelAmounts = $this->input('level_amounts');

                if (! is_array($levelAmounts) || $levelAmounts === []) {
                    return;
                }

                $knownCodes = Level::on('tenant')->pluck('code')->all();
                $unknownCodes = array_diff(array_map('strval', array_keys($levelAmounts)), $knownCodes);

                if ($unknownCodes !== []) {
                    $validator->errors()->add('level_amounts', 'Niveau(x) inconnu(s) : '.implode(', ', $unknownCodes).'.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Le code est obligatoire.',
            'code.unique' => 'Ce code est déjà utilisé par un autre type de frais.',
            'name.required' => 'Le libellé est obligatoire.',
            'default_amount.required' => 'Le montant par défaut est obligatoire.',
            'default_amount.min' => 'Le montant doit être positif.',
            'category.required' => 'La catégorie est obligatoire.',
            'category.in' => 'Catégorie invalide.',
            'academic_year_id.exists' => 'Année scolaire introuvable.',
            'levels.*.exists' => 'Niveau inconnu.',
            'level_amounts.*.numeric' => 'Le montant par niveau doit être un nombre.',
            'level_amounts.*.min' => 'Le montant par niveau doit être positif.',
        ];
    }

    /**
     * Attributs prêts à enregistrer (levels / level_amounts fusionnés dans applies_to).
     *
     * @param  array<string, mixed>|null  $currentAppliesTo
     * @return array<string, mixed>
     */
    public function feeTypeAttributes(?array $currentAppliesTo = null): array
    {
        $attributes = $this->safe()->only([
            'code', 'name', 'description', 'default_amount', 'category', 'is_mandatory', 'academic_year_id',
        ]);

        $hasLevels = $this->has('levels');
        $hasLevelAmounts = $this->has('level_amounts');

        if (! $this->has('applies_to') && ! $hasLevels && ! $hasLevelAmounts) {
            return $attributes;
        }

        $appliesTo = $this->has('applies_to')
            ? (array) $this->input('applies_to', [])
            : (array) ($currentAppliesTo ?? []);

        if ($hasLevels) {
            $appliesTo['levels'] = array_values(array_unique((array) $this->input('levels', [])));
        }

        if ($hasLevelAmounts) {
            $appliesTo['level_amounts'] = array_map(
                fn ($amount): float => (float) $amount,
                array_filter(
                    (array) $this->input('level_amounts', []),
                    fn ($amount): bool => $amount !== null && $amount !== ''
                )
            );
        }

        $attributes['applies_to'] = $appliesTo;

        return $attributes;
    }
}
