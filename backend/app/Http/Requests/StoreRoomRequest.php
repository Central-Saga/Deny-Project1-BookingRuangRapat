<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

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
            'image'        => [
                'sometimes',
                'nullable',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
                'max:5120',
            ],
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
