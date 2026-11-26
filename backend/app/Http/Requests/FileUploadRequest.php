<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    /**
     * Batasan maksimum ukuran file dalam kilobyte (5 MB = 5120 KB).
     * Guard maxSize untuk keamanan unggah berkas.
     */
    public const MAX_SIZE_KB = 5120;

    public const MAX_SIZE_BYTES = 5242880;

    /**
     * Tipe MIME yang diizinkan (accept guard).
     */
    public const ACCEPTED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validasi unggah berkas dengan guard tipe, dimensi, ekstensi, dan maxSize.
     */
    public function rules(): array
    {
        $fileRules = [
            'required',
            'file',
            'image',
            'mimes:jpeg,jpg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            'max:'.self::MAX_SIZE_KB,
        ];

        return [
            'file' => $fileRules,
            'is_private' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'in:public,private'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File wajib diunggah.',
            'file.file' => 'Input harus berupa file yang valid.',
            'file.image' => 'File yang diunggah harus berupa gambar.',
            'file.mimes' => 'Format file harus berupa JPEG, JPG, PNG, atau WEBP.',
            'file.mimetypes' => 'Format file harus berupa JPEG, PNG, atau WEBP.',
            'file.dimensions' => 'Dimensi gambar harus minimal 100x100 dan maksimal 4000x4000 piksel.',
            'file.max' => 'Ukuran file tidak boleh melebihi 5 MB (maxSize: 5120 KB).',
        ];
    }
}
