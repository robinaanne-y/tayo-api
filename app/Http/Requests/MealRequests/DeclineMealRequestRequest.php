<?php

namespace App\Http\Requests\MealRequests;

use Illuminate\Foundation\Http\FormRequest;

class DeclineMealRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actOnMealRequest', [$this->route('household'), $this->route('mealRequest')]);
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string'],
        ];
    }
}
