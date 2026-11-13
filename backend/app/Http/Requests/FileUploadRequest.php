<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi (mimetypes), dimensi (dimensions), dan ukuran (max:)
            'file' => 'required|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File wajib diunggah.',
            'file.file' => 'Input harus berupa file yang valid.',
            'file.mimetypes' => 'Format file harus berupa JPEG, PNG, atau WEBP.',
            'file.dimensions' => 'Dimensi gambar harus minimal 100x100 dan maksimal 4000x4000 piksel.',
            'file.max' => 'Ukuran file tidak boleh melebihi 5 MB.',
        ];
    }
}
