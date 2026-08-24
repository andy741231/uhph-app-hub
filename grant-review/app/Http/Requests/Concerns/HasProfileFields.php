<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared validation rules for user profile fields.
 *
 * Use in any FormRequest that accepts the extended profile fields:
 *   use HasProfileFields;
 *   public function rules(): array {
 *       return array_merge($this->profileRules(), [ ...other rules... ]);
 *   }
 */
trait HasProfileFields
{
    protected function profileRules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'peoplesoft_id' => ['nullable', 'string', 'size:6'],
            'investigator_type' => ['nullable', 'in:early_stage,new'],
        ];
    }

    protected function profileMessages(): array
    {
        return [
            'peoplesoft_id.size' => 'PeopleSoft ID must be exactly 6 digits.',
            'investigator_type.in' => 'Please select Early-Stage Investigator or New Investigator.',
        ];
    }
}
