<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDefaultHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'household_id' => [
                'nullable',
                'integer',
                Rule::exists('household_memberships', 'household_id')
                    ->where(fn ($query) => $query->where('member_id', $this->user()->member?->id)),
            ],
        ];
    }
}
