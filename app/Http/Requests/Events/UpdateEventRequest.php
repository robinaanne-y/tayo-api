<?php

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateEvent', [$this->route('household'), $this->route('event')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'visibility' => ['required', Rule::in(['private', 'household'])],
            'participant_member_ids' => ['sometimes', 'array'],
            'participant_member_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('participant_member_ids', []);
            if (empty($ids)) {
                return;
            }

            $memberIds = $this->route('household')->members()->pluck('members.id')->all();
            $invalidIds = array_diff($ids, $memberIds);

            if (!empty($invalidIds)) {
                $validator->errors()->add(
                    'participant_member_ids',
                    'One or more participants do not belong to this household.',
                );
            }
        });
    }
}
