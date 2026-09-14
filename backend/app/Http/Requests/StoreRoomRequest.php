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
            'name'        => 'required|string|max:255',
            'capacity'    => 'required|integer|min:1',
            'location'    => 'required|string|max:255',
            'facilities'  => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ];
    }
}