<?php

namespace App\Domains\Device\Requests;

use App\Domains\Device\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
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
    }

    public function rules(): array
    {
        $isMoodChecker = $this->isMoodCheckerType();
        $keyLength = $isMoodChecker
            ? Device::DEVICE_KEY_LENGTH_MOOD
            : Device::DEVICE_KEY_LENGTH_KIOSK;

        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', Rule::in(['kiosk', 'tv', 'mood_checker', 'mood-checker'])],
            'mood_mode' => ['nullable', 'string', Rule::in(['GENERAL', 'COUNTER', 'general', 'counter'])],
            'counter_id' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', Rule::in(['online', 'offline', 'maintenance'])],
            'region_id' => ['required', 'string', 'max:50'],
            'office_id' => ['required', 'string', 'max:50'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:devices,serial_number'],
            'ip_address' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'max:255'],
            'device_key' => [
                'nullable',
                'string',
                "size:{$keyLength}",
                "regex:/^[A-Z0-9]{{$keyLength}}$/",
                'unique:devices,device_key',
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        $isMoodChecker = $this->isMoodCheckerType();
        $keyLength = $isMoodChecker
            ? Device::DEVICE_KEY_LENGTH_MOOD
            : Device::DEVICE_KEY_LENGTH_KIOSK;

        return [
            'name.required' => 'Device name is required',
            'type.required' => 'Device type is required',
            'serial_number.required' => 'Serial number is required',
            'serial_number.unique' => 'Serial number already exists',
            'region_id.required' => 'Region is required',
            'office_id.required' => 'Office is required',
            'device_key.regex' => "The device key must be exactly {$keyLength} characters: uppercase letters and numbers only.",
            'device_key.size' => "The device key must be exactly {$keyLength} characters.",
        ];
    }

    private function isMoodCheckerType(): bool
    {
        $type = strtolower(str_replace('-', '_', (string) $this->input('type', '')));

        return in_array($type, ['mood_checker', 'mood'], true);
    }
}
