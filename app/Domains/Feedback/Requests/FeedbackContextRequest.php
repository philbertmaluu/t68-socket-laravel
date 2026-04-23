<?php

namespace App\Domains\Feedback\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:20'],
        ];
    }
}
