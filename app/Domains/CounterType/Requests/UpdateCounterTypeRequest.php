<?php

namespace App\Domains\CounterType\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCounterTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $counterTypeId = $this->route('counter_type');

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('counter_types', 'code')->ignore($counterTypeId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
        ];
    }
}
