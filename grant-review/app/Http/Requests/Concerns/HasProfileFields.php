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
            'peoplesoft_id' => ['nullable', 'regex:/^\d{7,20}$/'],
            'investigator_type' => ['nullable', 'in:pi,other'],
            'early_stage_investigator' => ['boolean'],
            'new_investigator' => ['boolean'],
            'key_personnel' => ['nullable', 'array'],
            'key_personnel.*.title' => ['nullable', 'string', 'max:255'],
            'key_personnel.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function profileMessages(): array
    {
        return [
            'peoplesoft_id.regex' => 'PeopleSoft ID must contain 7 to 20 digits.',
            'investigator_type.in' => 'Please select Principal Investigator or Other.',
        ];
    }
}
