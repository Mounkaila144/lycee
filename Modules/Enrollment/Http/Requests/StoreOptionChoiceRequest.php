<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionChoice;

class StoreOptionChoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'option_id' => ['required', 'integer', 'exists:tenant.options,id'],
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
            'choice_rank' => ['required', 'integer', 'in:1,2,3'],
            'motivation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $studentId = $this->input('student_id');
            $optionId = $this->input('option_id');
            $academicYearId = $this->input('academic_year_id');
            $choiceRank = $this->input('choice_rank');

            // Check if student already chose this option this year
            $existingChoice = OptionChoice::query()
                ->where('student_id', $studentId)
                ->where('option_id', $optionId)
                ->where('academic_year_id', $academicYearId)
                ->exists();

            if ($existingChoice) {
                $validator->errors()->add('option_id', 'Vous avez déjà sélectionné cette option.');
            }

            // Check if student already has a choice with this rank this year
            $existingRank = OptionChoice::query()
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->where('choice_rank', $choiceRank)
                ->exists();

            if ($existingRank) {
                $validator->errors()->add('choice_rank', "Vous avez déjà un vœu de rang {$choiceRank}.");
            }

            // Check if choice period is open
            $option = Option::find($optionId);
            if ($option && ! $option->isChoicePeriodOpen()) {
                $validator->errors()->add('option_id', 'La période de choix pour cette option n\'est pas ouverte.');
            }

            // Check max 3 choices per year
            $choiceCount = OptionChoice::query()
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->count();

            if ($choiceCount >= 3) {
                $validator->errors()->add('choice_rank', 'Vous avez atteint le maximum de 3 vœux.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'L\'étudiant est obligatoire.',
            'student_id.exists' => 'L\'étudiant sélectionné n\'existe pas.',
            'option_id.required' => 'L\'option est obligatoire.',
            'option_id.exists' => 'L\'option sélectionnée n\'existe pas.',
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'academic_year_id.exists' => 'L\'année académique n\'existe pas.',
            'choice_rank.required' => 'Le rang du vœu est obligatoire.',
            'choice_rank.in' => 'Le rang du vœu doit être 1, 2 ou 3.',
            'motivation.max' => 'La motivation ne doit pas dépasser 1000 caractères.',
        ];
    }
}
