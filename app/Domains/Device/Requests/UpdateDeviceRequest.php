<?php

namespace App\Domains\Device\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deviceId = $this->route('device');

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'string', Rule::in(['kiosk', 'tv'])],
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
            'notes' => ['nullable', 'string'],
        ];
    }
}
