<?php

namespace App\Http\Requests\GroceryItems;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroceryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addGroceryItem', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tripId = $this->input('trip_id');

            if ($tripId !== null) {
                $household = $this->route('household');
                $belongsToHousehold = $household->trips()->whereKey($tripId)->exists();

                if (! $belongsToHousehold) {
                    $validator->errors()->add('trip_id', 'The trip does not belong to this household.');
                }
            }
        });
    }
}
