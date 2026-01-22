<?php

namespace App\Domains\Device\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', Rule::in(['kiosk', 'tv'])],
            'status' => ['sometimes', 'string', Rule::in(['online', 'offline', 'maintenance'])],
            'region_id' => ['required', 'string', 'max:50'],
            'office_id' => ['required', 'string', 'max:50'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:devices,serial_number'],
            'ip_address' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Device name is required',
            'type.required' => 'Device type is required',
            'serial_number.required' => 'Serial number is required',
            'serial_number.unique' => 'Serial number already exists',
            'region_id.required' => 'Region ID is required',
            'office_id.required' => 'Office ID is required',
        ];
    }
}
