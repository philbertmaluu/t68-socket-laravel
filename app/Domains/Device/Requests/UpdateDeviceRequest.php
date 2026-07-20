<?php

namespace App\Domains\Device\Requests;

use App\Domains\Device\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
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
        $deviceId = $this->route('device');
        $keyLength = $this->deviceKeyLength();

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'string', Rule::in(['kiosk', 'tv', 'mood_checker', 'mood-checker'])],
            'mood_mode' => ['nullable', 'string', Rule::in(['GENERAL', 'COUNTER', 'general', 'counter'])],
            'counter_id' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', Rule::in(['online', 'offline', 'maintenance'])],
            'region_id' => ['sometimes', 'string', 'max:50'],
            'office_id' => ['sometimes', 'string', 'max:50'],
            'serial_number' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('devices', 'serial_number')->ignore($deviceId),
            ],
            'ip_address' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'max:255'],
            'device_key' => [
                'sometimes',
                'nullable',
                'string',
                "size:{$keyLength}",
                "regex:/^[A-Z0-9]{{$keyLength}}$/",
                Rule::unique('devices', 'device_key')->ignore($deviceId),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function deviceKeyLength(): int
    {
        $type = $this->input('type');

        if ($type === null && $this->route('device')) {
            $device = Device::query()->find($this->route('device'));
            $type = $device?->type;
        }

        return Device::deviceKeyLengthForType(is_string($type) ? $type : null);
    }
}
