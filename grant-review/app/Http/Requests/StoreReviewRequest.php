<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:1', 'max:9'],
            'comments' => ['nullable', 'string'],
            'factor1_score' => ['required', 'integer', 'min:1', 'max:9'],
            'factor1_comments' => ['nullable', 'string'],
            'factor2_score' => ['required', 'integer', 'min:1', 'max:9'],
            'factor2_comments' => ['nullable', 'string'],
            'factor3_sufficient' => ['required', 'boolean'],
            'factor3_comments' => ['nullable', 'string', 'required_if:factor3_sufficient,0'],
            'additional_human_subjects' => ['required', 'in:yes,no,na'],
            'additional_human_subjects_comments' => ['nullable', 'string'],
            'additional_vertebrate_animals' => ['required', 'in:yes,no,na'],
            'additional_vertebrate_animals_comments' => ['nullable', 'string'],
            'additional_biohazards' => ['required', 'in:yes,no,na'],
            'additional_biohazards_comments' => ['nullable', 'string'],
            'additional_resubmission' => ['required', 'in:yes,no,na'],
            'additional_resubmission_comments' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'factor3_comments.required_if' => 'An explanation is required when expertise and resources are not sufficient.',
        ];
    }
}
