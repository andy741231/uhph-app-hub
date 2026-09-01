<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isReviewer = $this->user()?->role === 'reviewer';

        $rules = [
            'phone' => ['required', 'string', 'max:20'],
            'department' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'peoplesoft_id' => ['required', 'regex:/^\d{7,20}$/'],
            'key_personnel' => ['nullable', 'array'],
            'key_personnel.*.title' => ['nullable', 'string', 'max:255'],
            'key_personnel.*.name' => ['nullable', 'string', 'max:255'],
        ];

        if (! $isReviewer) {
            $rules['investigator_type'] = ['required', 'in:pi,other'];
            $rules['early_stage_investigator'] = ['required', 'boolean'];
            $rules['new_investigator'] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'peoplesoft_id.regex' => 'PeopleSoft ID must contain 7 to 20 digits.',
            'investigator_type.in' => 'Please select Principal Investigator or Other.',
        ];
    }
}
