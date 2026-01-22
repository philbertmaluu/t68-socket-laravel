<?php

namespace App\Domains\Service\ServiceDocument\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['sometimes', 'exists:services,id'],
            'document_name' => ['sometimes', 'string', 'max:200'],
            'is_required' => ['sometimes', 'boolean'],
            'order_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
