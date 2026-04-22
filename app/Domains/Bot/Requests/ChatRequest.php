<?php

namespace App\Domains\Bot\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'array'],
            'context.office_id' => ['nullable', 'string', 'max:50'],
            'context.ticket_number' => ['nullable', 'string', 'max:50'],
            'context.service_id' => ['nullable', 'string', 'max:50'],
            'context.time_window' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => $validator->errors()->first(),
                'data' => [
                    'errors' => $validator->errors()->toArray(),
                ],
            ], 422)
        );
    }
}
