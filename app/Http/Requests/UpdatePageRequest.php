<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('page')->notebook->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'encrypted_content' => ['nullable', 'string'],
            'content_nonce' => ['nullable', 'string'],
            'date_mode' => ['nullable', 'string', 'in:dated,undated'],
            'page_date' => ['nullable', 'date'],
            'word_count' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
