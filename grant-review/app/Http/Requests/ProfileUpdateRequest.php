<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasProfileFields;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use HasProfileFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * Email is restricted to approved UH domains. Profile fields
     * (phone, department, title, peoplesoft_id, investigator_type)
     * are provided by the HasProfileFields trait.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return array_merge($this->profileRules(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $value = strtolower(trim((string) $value));
                    $allowed = ['@central.uh.edu', '@uh.edu', '@cougarnet.uh.edu'];
                    $valid = false;
                    foreach ($allowed as $domain) {
                        if (str_ends_with($value, $domain)) {
                            $valid = true;
                            break;
                        }
                    }
                    if (! $valid) {
                        $fail('Only @uh.edu, @central.uh.edu, or @cougarnet.uh.edu email addresses are allowed.');
                    }
                },
            ],
        ]);
    }

    public function messages(): array
    {
        return $this->profileMessages();
    }
}
