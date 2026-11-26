<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Audit 496 Parameter 14: Kepatuhan PSR-4 Autoloading Linux
 * Penamaan file StoreRoomRequest.php sesuai dengan nama kelas StoreRoomRequest.
 */
class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('stok') && ! $this->has('capacity')) {
            $merge['capacity'] = $this->input('stok');
        } elseif ($this->has('stock') && ! $this->has('capacity')) {
            $merge['capacity'] = $this->input('stock');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Audit 496 Tahap 11 (Param 11.8): Validasi isi file - aturan mimetypes wajib ada untuk verifikasi tipe file.
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'capacity' => $isUpdate ? 'sometimes|integer|min:1' : 'required|integer|min:1',
            'stok' => 'sometimes|integer|min:0',
            'stock' => 'sometimes|integer|min:0',
            'harga' => 'sometimes|numeric|min:0',
            'price' => 'sometimes|numeric|min:0',
            'saldo' => 'sometimes|numeric|min:0',
            'location' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            // Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi (mimetypes), dimensi (dimensions), dan ukuran (max:)
            'image' => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.min' => 'Kapasitas ruangan harus minimal 1 orang dan tidak boleh negatif.',
            'stok.min' => 'Stok ruangan tidak boleh bernilai negatif.',
            'stock.min' => 'Stok ruangan tidak boleh bernilai negatif.',
            'harga.min' => 'Harga tidak boleh bernilai negatif.',
            'price.min' => 'Harga tidak boleh bernilai negatif.',
            'saldo.min' => 'Saldo tidak boleh bernilai negatif.',
            'image.mimetypes' => 'Format gambar ruangan harus JPEG, PNG, atau WEBP.',
            'image.dimensions' => 'Dimensi gambar ruangan minimal 100x100 dan maksimal 4000x4000 piksel.',
            'image.max' => 'Ukuran gambar ruangan maksimal 5 MB.',
        ];
    }
}
