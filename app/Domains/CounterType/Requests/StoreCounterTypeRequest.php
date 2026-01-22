<?php

namespace App\Domains\CounterType\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCounterTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:50', 'unique:counter_types,code'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Counter type name is required',
            'code.required' => 'Code is required',
            'code.unique' => 'Code already exists',
        ];
    }
}
