<?php

namespace App\Domains\Feedback\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketFeedbackQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'string', 'max:50'],
        ];
    }
}
