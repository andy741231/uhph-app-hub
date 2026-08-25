<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasProfileFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use HasProfileFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        $rules = array_merge($this->profileRules(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => config('hub.enabled')
                ? ['required', 'email', Rule::in([$this->route('user')->email])]
                : ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'round_ids' => ['sometimes', 'array'],
            'round_ids.*' => ['exists:rounds,id'],
        ]);

        $rules['role'] = ['required', 'in:admin,submitter,reviewer'];
        if (! config('hub.enabled')) {
            $rules['status'] = ['required', 'in:invited,active,disabled'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return $this->profileMessages();
    }
}
