<?php

namespace App\Http\Requests\PermissionRequests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateRequest', [$this->route('household'), $this->route('permissionRequest')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requested_start_at' => ['nullable', 'date'],
            'requested_end_at' => ['nullable', 'date', 'after_or_equal:requested_start_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasStart = $this->filled('requested_start_at');
            $hasEnd = $this->filled('requested_end_at');

            if ($hasStart !== $hasEnd) {
                $validator->errors()->add(
                    'requested_end_at',
                    'Provide both a start and end time, or neither.',
                );
            }
        });
    }
}
