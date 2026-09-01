<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasProfileFields;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetPasswordRequest extends FormRequest
{
    use HasProfileFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Override profile fields to be required for initial setup
        $requiredProfile = [
            'phone' => ['required', 'string', 'max:20'],
            'department' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'peoplesoft_id' => ['required', 'regex:/^\d{7,20}$/'],
        ];

        // Investigator fields are not required for reviewers
        $user = User::where('email', $this->email)->first();
        if ($user?->role !== 'reviewer') {
            $requiredProfile['investigator_type'] = ['required', 'in:pi,other'];
            $requiredProfile['early_stage_investigator'] = ['boolean'];
            $requiredProfile['new_investigator'] = ['boolean'];
        }

        return array_merge($requiredProfile, [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->profileMessages(), [
            'phone.required' => 'The phone number field is required.',
            'department.required' => 'The department field is required.',
            'title.required' => 'The title field is required.',
            'peoplesoft_id.required' => 'The PeopleSoft ID field is required.',
            'investigator_type.required' => 'Please select an investigator type.',
        ]);
    }
}
