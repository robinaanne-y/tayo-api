<?php

namespace App\Http\Requests\Households;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:16'],
            'meal_approver_member_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('household_memberships', 'member_id')
                    ->where(fn ($query) => $query->where('household_id', $this->route('household')->id)),
            ],
        ];
    }
}
