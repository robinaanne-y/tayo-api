<?php

namespace App\Http\Requests\MealRequests;

use App\Enums\MealSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMealRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateMealRequest', [$this->route('household'), $this->route('mealRequest')]);
    }

    public function rules(): array
    {
        return [
            'requested_date' => ['required', 'date'],
            'requested_slot' => ['required', Rule::in(array_column(MealSlot::cases(), 'value'))],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
