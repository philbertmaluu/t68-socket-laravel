<?php

namespace App\Domains\Mood\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMoodCounterFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'session_id' => ['nullable', 'uuid'],
            'ticket_id' => ['nullable', 'integer'],
            'counter_id' => ['nullable', 'string', 'max:50'],
            'officer_id' => ['nullable', 'string', 'max:50'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_option_id' => ['nullable', 'integer'],
            'reason_id' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'created_at' => ['nullable', 'date'],
            'synced_from_offline' => ['nullable', 'boolean'],
        ];
    }
}
