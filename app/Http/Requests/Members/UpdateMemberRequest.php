<?php

namespace App\Http\Requests\Members;

use App\Enums\HouseholdRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMember', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            // Omitted entirely when editing the Owner's own row — that role
            // is immutable (see MemberController::update()), and it must
            // never be reachable as a value here either, since that would
            // let a non-Owner member be promoted via this endpoint.
            'role' => [
                'sometimes',
                'required',
                new Enum(HouseholdRole::class),
                Rule::notIn([HouseholdRole::Owner->value]),
            ],
        ];
    }
}
