<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addTripMemory', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
