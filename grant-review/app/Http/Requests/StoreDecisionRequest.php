<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['sometimes', 'exists:submissions,id'],
            'outcome' => ['required', 'in:funded,not_funded'],
            'amount_awarded' => ['required_if:outcome,funded', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
