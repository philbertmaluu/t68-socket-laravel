<?php

namespace App\Domains\Mood\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoodLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string'],
            'device_uuid' => ['nullable', 'string', 'max:64'],
        ];
    }
}
