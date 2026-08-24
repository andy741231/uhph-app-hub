<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReviewersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_ids' => ['nullable', 'array'],
            'reviewer_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
