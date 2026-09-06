<?php

namespace App\Http\Requests\Invitations;

use App\Enums\HouseholdRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMember', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                new Enum(HouseholdRole::class),
                Rule::notIn([HouseholdRole::Owner->value]),
            ],
        ];
    }
}
