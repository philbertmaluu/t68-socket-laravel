<?php

namespace App\Domains\Counter\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'counter_type_id' => ['sometimes', 'exists:counter_types,id'],
            'service_ids' => ['sometimes', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
            'office_id' => ['sometimes', 'string', 'max:50'],
            'clerk_id' => ['nullable', 'string', 'max:50'],
        ];
    }
}
