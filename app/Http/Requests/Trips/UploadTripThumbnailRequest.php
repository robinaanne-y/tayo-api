<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;

class UploadTripThumbnailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTrip', [$this->route('household'), $this->route('trip')]);
    }

    public function rules(): array
    {
        return [
            'thumbnail' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
