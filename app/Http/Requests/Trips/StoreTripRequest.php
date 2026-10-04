<?php

namespace App\Http\Requests\Trips;

use App\Enums\TripStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addTrip', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(array_column(TripStatus::cases(), 'value'))],
            'participant_member_ids' => ['sometimes', 'array'],
            'participant_member_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $participantIds = $this->input('participant_member_ids', []);

            if (! empty($participantIds)) {
                $memberIds = $this->route('household')->members()->pluck('members.id')->all();
                $invalidMemberIds = array_diff($participantIds, $memberIds);

                if (! empty($invalidMemberIds)) {
                    $validator->errors()->add(
                        'participant_member_ids',
                        'One or more participants do not belong to this household.',
                    );
                }
            }
        });
    }
}
