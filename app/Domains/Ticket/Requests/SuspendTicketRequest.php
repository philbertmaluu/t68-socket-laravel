<?php

namespace App\Domains\Ticket\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspendTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Suspension reason is required',
            'reason.min' => 'Suspension reason must be at least 20 characters',
            'reason.max' => 'Suspension reason may not exceed 500 characters',
        ];
    }
}
