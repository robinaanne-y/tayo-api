<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTask', [$this->route('household'), $this->route('task')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_member_id' => ['nullable', 'integer'],
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
        });
    }
}
