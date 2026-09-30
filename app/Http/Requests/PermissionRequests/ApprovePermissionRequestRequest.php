<?php

namespace App\Http\Requests\PermissionRequests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePermissionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actOnRequest', [$this->route('household'), $this->route('permissionRequest')]);
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*' => ['string'],
            'create_event' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->boolean('create_event')) {
                return;
            }

            $permissionRequest = $this->route('permissionRequest');

            if ($permissionRequest->requested_start_at === null || $permissionRequest->requested_end_at === null) {
                $validator->errors()->add(
                    'create_event',
                    'This request has no time window to create an event from.',
                );
            }
        });
    }
}
