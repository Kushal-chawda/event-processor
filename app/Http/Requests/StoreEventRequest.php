<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'payload.required' => 'payload is required',
            'payload.string' => 'payload must be a string',
        ];
    }
}
