<?php

namespace App\Http\Requests\Notes;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addNote', $this->route('household'));
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:280'],
        ];
    }
}
