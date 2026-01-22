<?php

namespace App\Domains\Counter\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'exists:counter_types,id'],
            'service_id' => ['required', 'exists:services,id'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
            'office_id' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Counter name is required',
            'type.required' => 'Counter type is required',
            'type.exists' => 'Selected counter type does not exist',
            'service_id.required' => 'Service is required',
            'service_id.exists' => 'Selected service does not exist',
            'office_id.required' => 'Office ID is required',
        ];
    }
}
