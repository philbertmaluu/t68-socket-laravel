<?php

namespace App\Domains\Notification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled via route middleware / policies.
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:191'],
            'channel' => ['required', 'string', 'max:50'],
            'locale' => ['required', 'string', 'max:10'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }
}

