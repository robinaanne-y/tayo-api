<?php

namespace App\Http\Requests\PermissionRequests;

use Illuminate\Foundation\Http\FormRequest;

class DeclinePermissionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actOnRequest', [$this->route('household'), $this->route('permissionRequest')]);
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string'],
        ];
    }
}
