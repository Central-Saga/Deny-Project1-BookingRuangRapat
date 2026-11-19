<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Audit 496 Parameter 14:
 * FormRequest CancelBookingRequest Wajib Mengisi Alasan Pembatalan (minimal 10 karakter).
 */
class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancellation_reason.min'      => 'Alasan pembatalan minimal 10 karakter.',
            'cancellation_reason.max'      => 'Alasan pembatalan maksimal 500 karakter.',
        ];
    }
}
