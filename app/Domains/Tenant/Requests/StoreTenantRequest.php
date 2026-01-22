<?php

namespace App\Domains\Tenant\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'max:50', 'unique:tenants,id'],
            'name' => ['required', 'string', 'max:200'],
            'domain' => ['required', 'string', 'max:255', 'unique:tenants,domain'],
            'database' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tenant name is required',
            'domain.required' => 'Domain is required',
            'domain.unique' => 'Domain already exists',
        ];
    }
}
