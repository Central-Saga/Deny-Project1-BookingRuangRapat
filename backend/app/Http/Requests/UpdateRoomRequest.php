<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Audit 496 Parameter SEC (Issue #177):
 * FormRequest terpisah untuk Update Ruangan dengan validasi lengkap.
 */
class UpdateRoomRequest extends FormRequest
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

    public function rules(): array
    {
        $roomId = $this->route('room') instanceof Room ? $this->route('room')->id : ($this->route('room') ?? $this->input('id') ?? $this->input('room_id'));

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('rooms', 'name')->ignore($roomId)->whereNull('deleted_at')],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'stok' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'harga' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'saldo' => ['sometimes', 'numeric', 'min:0'],
            'location' => ['sometimes', 'string', 'max:255'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000', 'max:5120'],
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
