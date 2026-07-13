<?php

namespace App\Domains\Ticket\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
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
            'service_type_id' => ['required', 'string', 'max:50', 'exists:services,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'office_id' => ['required', 'string', 'max:50'],
            'locale' => ['required', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_type_id.required' => 'Service type ID is required',
            'service_type_id.exists' => 'The selected service type does not exist',
            'phone_number.required' => 'Phone number is required',
            'office_id.required' => 'Office ID is required',
            'locale.required' => 'Locale is required',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        Log::warning('POST /api/qms/tickets validation failed', [
            'payload' => $this->all(),
            'errors' => $validator->errors()->toArray(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => $validator->errors()->first() . ' and ' . $validator->errors()->count() . ' more error',
                'data' => [
                    'errors' => $validator->errors()->toArray()
                ]
            ], 422)
        );
    }
}
