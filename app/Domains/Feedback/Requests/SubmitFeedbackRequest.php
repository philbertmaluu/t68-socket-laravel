<?php

namespace App\Domains\Feedback\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment_key' => $this->input('comment_key') ?? $this->input('category_key'),
            'comment_label' => $this->input('comment_label') ?? $this->input('category_label'),
            'comment_text' => $this->input('comment_text') ?? $this->input('comments'),
        ]);
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
