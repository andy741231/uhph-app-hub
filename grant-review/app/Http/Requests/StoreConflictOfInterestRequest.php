<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConflictOfInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conflicts' => ['sometimes', 'array'],
            'conflicts.*.submission_id' => ['required_with:conflicts', 'integer', 'exists:submissions,id'],
            'conflicts.*.has_conflict' => ['required_with:conflicts', 'boolean'],
            'conflicts.*.description' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'conflicts.*.description.max' => 'Please keep each conflict description under 2,000 characters.',
        ];
    }
}
