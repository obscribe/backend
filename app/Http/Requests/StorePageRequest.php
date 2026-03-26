<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('notebook')->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'encrypted_content' => ['nullable', 'string'],
            'content_nonce' => ['nullable', 'string'],
            'date_mode' => ['nullable', 'string', 'in:dated,undated'],
            'page_date' => ['nullable', 'date'],
            'template_type' => ['nullable', 'string', 'in:blank,lined,dotted,grid'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
