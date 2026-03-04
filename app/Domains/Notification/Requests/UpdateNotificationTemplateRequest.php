<?php

namespace App\Domains\Notification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled via route middleware / policies.
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['sometimes', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

