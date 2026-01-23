<?php

namespace App\Domains\Authentication\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'exists:roles,id'],
            'to_user_id' => ['required', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_ids.required' => 'At least one role ID is required',
            'role_ids.array' => 'Role IDs must be an array',
            'role_ids.min' => 'At least one role must be selected',
            'role_ids.*.required' => 'Each role ID is required',
            'role_ids.*.exists' => 'One or more selected roles do not exist',
            'to_user_id.required' => 'Recipient user ID is required',
            'to_user_id.exists' => 'Recipient user does not exist',
        ];
    }
}
