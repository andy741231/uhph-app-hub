<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
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
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                function (string $attribute, mixed $value, Closure $fail) {
                    $allowed = ['@uh.edu', '@central.uh.edu', '@cougarnet.uh.edu'];
                    $valid = false;
                    foreach ($allowed as $domain) {
                        if (str_ends_with(strtolower($value), $domain)) {
                            $valid = true;
                            break;
                        }
                    }
                    if (! $valid) {
                        $fail('Only @uh.edu, @central.uh.edu, or @cougarnet.uh.edu email addresses are allowed.');
                    }
                },
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,submitter,reviewer'],
            'round_ids' => ['required_if:role,submitter', 'array', 'min:1'],
            'round_ids.*' => ['exists:rounds,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'round_ids.required_if' => 'Select at least one cycle for submitters.',
            'round_ids.min' => 'Select at least one cycle for submitters.',
        ];
    }
}
