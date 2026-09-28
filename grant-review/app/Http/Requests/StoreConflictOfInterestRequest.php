<?php

namespace App\Http\Requests;

use App\Models\Round;
use Illuminate\Foundation\Http\FormRequest;

class StoreConflictOfInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $round = $this->route('round');
        $submissionIds = $round instanceof Round
            ? $round->submissions()->whereIn('status', ['submitted', 'under_review', 'decided'])->pluck('id')
            : collect();

        $rules = [
            // Every screened proposal needs an explicit Conflict / No conflict
            // choice — an omitted row would otherwise silently record "clear".
            'conflicts' => [$submissionIds->isEmpty() ? 'sometimes' : 'required', 'array'],
            'conflicts.*.submission_id' => ['required_with:conflicts', 'integer', 'exists:submissions,id'],
            'conflicts.*.has_conflict' => ['sometimes', 'boolean'],
            'conflicts.*.description' => ['required_if:conflicts.*.has_conflict,1', 'nullable', 'string', 'max:2000'],
            'coi_policy_acknowledged' => ['required', 'accepted'],
            'confidentiality_acknowledged' => ['required', 'accepted'],
            'return_to' => ['sometimes', 'string'],
        ];

        foreach ($submissionIds as $id) {
            $rules["conflicts.{$id}.has_conflict"] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'conflicts.*.has_conflict.required' => 'Select either Conflict or No conflict for every proposal.',
            'conflicts.*.description.required_if' => 'Please briefly describe the conflict of interest.',
            'conflicts.*.description.max' => 'Please keep each conflict description under 2,000 characters.',
            'coi_policy_acknowledged.required' => 'Please read and agree to the COI Policy & Guidelines.',
            'coi_policy_acknowledged.accepted' => 'Please read and agree to the COI Policy & Guidelines.',
            'confidentiality_acknowledged.required' => 'Please read and agree to the Confidentiality Statement & Code of Conduct.',
            'confidentiality_acknowledged.accepted' => 'Please read and agree to the Confidentiality Statement & Code of Conduct.',
        ];
    }
}
