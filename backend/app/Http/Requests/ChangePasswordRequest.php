<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Audit 496 Parameter SEC (Issue #177):
 * FormRequest untuk perubahan password dengan Password::min(8).
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('old_password') && ! $this->has('current_password')) {
            $merge['current_password'] = $this->input('old_password');
        }
        if ($this->has('new_password') && ! $this->has('password')) {
            $merge['password'] = $this->input('new_password');
        }
        if ($this->has('new_password_confirmation') && ! $this->has('password_confirmation')) {
            $merge['password_confirmation'] = $this->input('new_password_confirmation');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)],
            'password_confirmation' => ['sometimes', 'nullable', 'string', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal harus 8 karakter.',
            'password_confirmation.same' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
