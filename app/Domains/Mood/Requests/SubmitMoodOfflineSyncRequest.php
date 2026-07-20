<?php

namespace App\Domains\Mood\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMoodOfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.client_uuid' => ['required', 'uuid'],
            'items.*.type' => ['required', 'string', 'in:general,counter'],
            'items.*.payload' => ['nullable', 'array'],
        ];
    }
}
