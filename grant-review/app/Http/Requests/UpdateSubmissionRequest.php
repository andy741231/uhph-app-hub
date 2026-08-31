<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'abstract' => ['sometimes', 'required', 'string', 'max:65000'],
            'amount_requested' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'pdf' => ['sometimes', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }
}
