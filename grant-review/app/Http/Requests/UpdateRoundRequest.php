<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'opens_at' => ['sometimes', 'required', 'date'],
            'deadline_at' => ['sometimes', 'required', 'date', 'after:opens_at'],
            'status' => ['sometimes', 'in:draft,open,closed'],
        ];
    }
}
