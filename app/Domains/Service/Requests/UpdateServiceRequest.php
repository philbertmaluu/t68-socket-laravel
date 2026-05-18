<?php

namespace App\Domains\Service\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'estimated_time' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
        ];
    }
}
