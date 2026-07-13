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
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Please select a service',
            'service_id.exists' => 'The selected service does not exist in the catalog',
        ];
    }
}
