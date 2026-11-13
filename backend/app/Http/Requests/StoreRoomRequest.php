<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Audit 496 Tahap 11 (Param 11.8): Validasi isi file - aturan mimetypes wajib ada untuk verifikasi tipe file.
     */
    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'capacity'     => 'required|integer|min:1',
            'location'     => 'required|string|max:255',
            'facilities'   => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'description'  => 'nullable|string',
            'is_active'    => 'boolean',
            // Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi (mimetypes), dimensi (dimensions), dan ukuran (max:)
            'image'        => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimetypes' => 'Format gambar ruangan harus JPEG, PNG, atau WEBP.',
            'image.dimensions' => 'Dimensi gambar ruangan minimal 100x100 dan maksimal 4000x4000 piksel.',
            'image.max' => 'Ukuran gambar ruangan maksimal 5 MB.',
        ];
    }
}
