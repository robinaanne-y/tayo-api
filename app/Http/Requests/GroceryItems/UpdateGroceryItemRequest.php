<?php

namespace App\Http\Requests\GroceryItems;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroceryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageGroceryItem', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }
}
