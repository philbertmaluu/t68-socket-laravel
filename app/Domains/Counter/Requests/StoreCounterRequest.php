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
            'counter_type_id' => ['required', 'exists:counter_types,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
            'clerk_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Counter name is required',
            'counter_type_id.required' => 'Counter type is required',
            'counter_type_id.exists' => 'Selected counter type does not exist',
            'service_ids.required' => 'At least one service is required',
            'service_ids.*.exists' => 'One or more selected services do not exist',
        ];
    }
}
