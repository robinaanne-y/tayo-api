<?php

namespace App\Http\Requests\Auth;

use App\Enums\ReminderCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_column(ReminderCategory::cases(), 'value'))],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
