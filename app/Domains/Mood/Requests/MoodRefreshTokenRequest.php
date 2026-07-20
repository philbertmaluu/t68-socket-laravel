<?php

namespace App\Domains\Mood\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoodRefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
