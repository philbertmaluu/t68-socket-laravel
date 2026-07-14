<?php

namespace App\Domains\Device\Requests;

use App\Domains\Device\Services\DeviceAuthAuditor;
use Illuminate\Contracts\Validation\Validator;
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
            // No format/size rules — lookup fails naturally if the key is wrong.
            'device_key' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:200'],
            'password' => ['nullable', 'string', 'max:255'],
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

    protected function failedValidation(Validator $validator): void
    {
        DeviceAuthAuditor::failed(
            $this->only(['device_key', 'name', 'password']),
            'Validation failed',
            null,
            ['validation_errors' => $validator->errors()->toArray()],
        );

        parent::failedValidation($validator);
    }
}
