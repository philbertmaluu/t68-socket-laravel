<?php

namespace App\Domains\Service\ServiceDocument\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'document_name' => ['required', 'string', 'max:200'],
            'is_required' => ['sometimes', 'boolean'],
            'order_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Service ID is required',
            'service_id.exists' => 'Selected service does not exist',
            'document_name.required' => 'Document name is required',
        ];
    }
}
