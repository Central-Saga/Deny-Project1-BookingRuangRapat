<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Audit 496 Parameter SEC (Issue #177):
 * FormRequest untuk pembuatan temporary URL file upload.
 */
class TemporaryUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string'],
            'expires_in' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ];
    }
}
