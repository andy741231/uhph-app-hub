<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'deadline_at' => ['required', 'date', 'after:opens_at'],
            'status' => ['sometimes', 'in:draft,open,closed'],
        ];
    }
}
