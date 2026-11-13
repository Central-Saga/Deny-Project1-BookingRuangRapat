<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'role'   => ['sometimes', 'in:admin,karyawan'],
            'status' => ['sometimes', 'in:active,inactive'],
            'avatar' => [
                'sometimes',
                'nullable',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=50,min_height=50,max_width=2000,max_height=2000',
                'max:2048',
            ],
        ];
    }
}
