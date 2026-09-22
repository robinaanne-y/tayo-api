<?php

namespace App\Http\Requests\Households;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Household::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Cosmetic only — a hex color and an emoji chosen from a fixed
            // client-side palette, not validated against a specific set so
            // the palette can change without a backend deploy.
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:16'],
        ];
    }
}
