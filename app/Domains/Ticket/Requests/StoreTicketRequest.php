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
            'ticket_number' => ['required', 'string', 'max:50'],
            'service_type' => ['required', 'string', 'max:200'],
            'service_id' => ['nullable', 'string', 'max:50'],
            'queue_id' => ['required', 'string', 'max:50'],
            'member_number' => ['nullable', 'string', 'max:50'],
            'member_name' => ['nullable', 'string', 'max:200'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'estimated_time' => ['nullable', 'integer'],
            'priority' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['waiting', 'called', 'serving', 'completed', 'skipped', 'transferred', 'cancelled'])],
            'counter_id' => ['nullable', 'string', 'max:50'],
            'clerk_id' => ['nullable', 'string', 'max:50'],
            'office_id' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_number.required' => 'Ticket number is required',
            'service_type.required' => 'Service type is required',
            'queue_id.required' => 'Queue ID is required',
            'office_id.required' => 'Office ID is required',
        ];
    }
}
