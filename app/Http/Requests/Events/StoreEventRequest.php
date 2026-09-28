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
            // Deliberately just 'array' here, not 'required_if'/'min:1' —
            // those run whenever the field is *present*, regardless of
            // their own condition, and the mobile client always sends this
            // key (as [] when unused). The "must be non-empty when
            // visibility is selected_households" check lives in
            // withValidator() below instead, where it can actually be
            // conditional on visibility.
            'shared_household_ids' => ['array'],
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

            if ($this->input('visibility') === 'selected_households' && empty($sharedHouseholdIds)) {
                $validator->errors()->add(
                    'shared_household_ids',
                    'Select at least one household to share with.',
                );
            }

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
