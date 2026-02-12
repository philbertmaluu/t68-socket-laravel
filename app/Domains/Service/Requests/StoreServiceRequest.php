<?php

namespace App\Domains\Service\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'estimated_time' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
            // Allow nullable/empty - defaults will be set in ServiceService if missing
            'region_id' => ['nullable', 'string', 'max:50'],
            'office_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required',
            'estimated_time.required' => 'Estimated time is required',
            'estimated_time.min' => 'Estimated time must be at least 1 minute',
        ];
    }
}
