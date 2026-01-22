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
            'type' => ['sometimes', 'exists:counter_types,id'],
            'service_id' => ['sometimes', 'exists:services,id'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
            'office_id' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
