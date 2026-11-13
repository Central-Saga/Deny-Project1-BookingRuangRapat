<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Audit 496 Tahap 11 (Param 11.8): Validasi isi file - aturan mimetypes wajib ada untuk mencegah file spoofing.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
                'max:5120',
            ],
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
