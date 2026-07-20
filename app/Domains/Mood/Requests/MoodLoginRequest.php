<?php

namespace App\Domains\Mood\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class MoodLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('device_key') && is_string($this->input('device_key'))) {
            $this->merge([
                'device_key' => strtoupper(trim($this->input('device_key'))),
            ]);
        }

        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Accept legacy 10-char keys until all devices are normalized to 5.
            'device_key' => ['nullable', 'string', 'min:5', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'name' => ['nullable', 'string', 'max:200'],
            'password' => ['nullable', 'string'],
            'device_uuid' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasKey = !empty($this->input('device_key'));
            $hasName = !empty($this->input('name'));
            $hasPassword = $this->has('password');

            if ($hasKey && ($hasName || $hasPassword)) {
                $validator->errors()->add(
                    'device_key',
                    'Provide either device_key or name and password, not both.'
                );

                return;
            }

            if (!$hasKey && !($hasName && $hasPassword)) {
                $validator->errors()->add(
                    'name',
                    'Provide either device_key or both name and password.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        parent::failedValidation($validator);
    }
}
