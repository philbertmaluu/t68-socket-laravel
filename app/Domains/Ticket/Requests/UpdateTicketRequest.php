<?php

namespace App\Domains\Ticket\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_number' => ['sometimes', 'string', 'max:50'],
            'service_type' => ['sometimes', 'string', 'max:200'],
            'service_id' => ['nullable', 'string', 'max:50'],
            'queue_id' => ['sometimes', 'string', 'max:50'],
            'member_number' => ['nullable', 'string', 'max:50'],
            'member_name' => ['nullable', 'string', 'max:200'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'estimated_time' => ['nullable', 'integer'],
            'priority' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['waiting', 'called', 'serving', 'completed', 'skipped', 'transferred', 'cancelled'])],
            'counter_id' => ['nullable', 'string', 'max:50'],
            'clerk_id' => ['nullable', 'string', 'max:50'],
            'office_id' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
