<?php

namespace App\Http\Requests\MealPlanItems;

use App\Enums\MealSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealPlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMealPlanItem', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'slot' => ['required', Rule::in(array_column(MealSlot::cases(), 'value'))],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
