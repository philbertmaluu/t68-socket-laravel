<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_number' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'challenge_id' => ['nullable', 'string', 'max:36'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status_code' => 422,
            'message' => $validator->errors()->first(),
            'data' => ['errors' => $validator->errors()->toArray()],
        ], 422));
    }
}
