<?php

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addEvent', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'visibility' => [
                'required',
                Rule::in(['private', 'household', 'selected_households', 'all_member_households']),
            ],
            'participant_member_ids' => ['sometimes', 'array'],
            'participant_member_ids.*' => ['integer'],
            'shared_household_ids' => ['required_if:visibility,selected_households', 'array', 'min:1'],
            'shared_household_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $participantIds = $this->input('participant_member_ids', []);
            if (!empty($participantIds)) {
                $memberIds = $this->route('household')->members()->pluck('members.id')->all();
                $invalidMemberIds = array_diff($participantIds, $memberIds);

                if (!empty($invalidMemberIds)) {
                    $validator->errors()->add(
                        'participant_member_ids',
                        'One or more participants do not belong to this household.',
                    );
                }
            }

            $sharedHouseholdIds = $this->input('shared_household_ids', []);
            if (!empty($sharedHouseholdIds)) {
                $myHouseholdIds = $this->user()->member->households()->pluck('households.id')->all();
                $invalidHouseholdIds = array_diff($sharedHouseholdIds, $myHouseholdIds);

                if (!empty($invalidHouseholdIds)) {
                    $validator->errors()->add(
                        'shared_household_ids',
                        'One or more households do not belong to you.',
                    );
                }
            }
        });
    }
}
