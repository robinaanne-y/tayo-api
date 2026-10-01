<?php

namespace App\Http\Requests\MealRequests;

use App\Enums\MealSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveMealRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actOnMealRequest', [$this->route('household'), $this->route('mealRequest')]);
    }

    public function rules(): array
    {
        return [
            'response_note' => ['nullable', 'string'],
            // Optional "move it to another day/slot" override -- defaults
            // to the request's own date/slot in the controller when
            // omitted. Both must be given together or not at all, same
            // reasoning as the time-window pairing on permission requests.
            'date' => ['nullable', 'date'],
            'slot' => ['nullable', Rule::in(array_column(MealSlot::cases(), 'value'))],
        ];
    }
}
