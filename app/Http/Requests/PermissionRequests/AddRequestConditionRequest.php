<?php

namespace App\Http\Requests\PermissionRequests;

use Illuminate\Foundation\Http\FormRequest;

class AddRequestConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addRequestCondition', [$this->route('household'), $this->route('permissionRequest')]);
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
        ];
    }
}
