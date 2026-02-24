<?php

namespace App\Domains\Device\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceAuthenticateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_key' => ['nullable', 'string', 'size:10', 'regex:/^[A-Z0-9]{10}$/'],
            'name' => ['nullable', 'string', 'max:200'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_key.regex' => 'The device key must be exactly 10 characters: uppercase letters and numbers only.',
            'device_key.size' => 'The device key must be exactly 10 characters.',
        ];
    }

    /**
     * Require either device_key or both name and password.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasKey = !empty($this->input('device_key'));
            $hasName = !empty($this->input('name'));
            $hasPassword = $this->has('password');

            if ($hasKey && ($hasName || $hasPassword)) {
                $validator->errors()->add('device_key', 'Provide either device_key or name and password, not both.');
                return;
            }
            if (!$hasKey && !($hasName && $hasPassword)) {
                $validator->errors()->add('name', 'Provide either device_key or both name and password.');
            }
        });
    }
}
