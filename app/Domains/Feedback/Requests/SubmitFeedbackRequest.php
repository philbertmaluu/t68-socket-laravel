<?php

namespace App\Domains\Feedback\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:20'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment_key' => ['nullable', 'string', 'max:100'],
            'comment_label' => ['nullable', 'string', 'max:255'],
            'comment_text' => ['nullable', 'string', 'max:2000'],
            'clerk_rating' => ['nullable', 'integer', 'between:1,5'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
