<?php

namespace App\Domains\Ticket\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type_id' => ['required', 'string', 'max:50', 'exists:services,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'office_id' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_type_id.required' => 'Service type ID is required',
            'service_type_id.exists' => 'The selected service type does not exist',
            'phone_number.required' => 'Phone number is required',
            'office_id.required' => 'Office ID is required',
        ];
    }
}
