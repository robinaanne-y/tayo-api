<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addTask', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_member_id' => ['nullable', 'integer'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],

            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence.interval' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'recurrence.by_day' => ['sometimes', 'array'],
            'recurrence.by_day.*' => ['integer', 'between:1,7'],
            'recurrence.ends_at' => ['nullable', 'date', 'after_or_equal:due_at'],
            'recurrence.occurrence_count' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $assignedMemberId = $this->input('assigned_member_id');

            if ($assignedMemberId !== null) {
                $memberIds = $this->route('household')->members()->pluck('members.id')->all();

                if (! in_array($assignedMemberId, $memberIds, true)) {
                    $validator->errors()->add(
                        'assigned_member_id',
                        'The assignee does not belong to this household.',
                    );
                }
            }

            $tripId = $this->input('trip_id');

            if ($tripId !== null) {
                $household = $this->route('household');
                $belongsToHousehold = $household->trips()->whereKey($tripId)->exists();

                if (! $belongsToHousehold) {
                    $validator->errors()->add('trip_id', 'The trip does not belong to this household.');
                }
            }

            $recurrence = $this->input('recurrence');

            if ($recurrence !== null) {
                $endsAt = $recurrence['ends_at'] ?? null;
                $occurrenceCount = $recurrence['occurrence_count'] ?? null;

                if (($endsAt === null) === ($occurrenceCount === null)) {
                    $validator->errors()->add(
                        'recurrence.ends_at',
                        'Choose either an end date or a number of occurrences, not both or neither.',
                    );
                }
            }
        });
    }
}
