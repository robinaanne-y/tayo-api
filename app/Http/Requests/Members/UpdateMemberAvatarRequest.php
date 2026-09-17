<?php

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMember', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
