<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripItineraryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTrip', [$this->route('household'), $this->route('trip')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
        ];
    }
}
