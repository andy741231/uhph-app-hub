<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'round_id' => ['required', 'exists:rounds,id'],
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['required', 'string'],
            'amount_requested' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'], // 20MB max
        ];
    }
}
