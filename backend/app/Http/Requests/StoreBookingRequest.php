<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Rules\WithinRoomCapacityRule;
use App\Rules\WithinWorkingHoursRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * Audit 496 Parameter 51: Sanitasi Input Judul & Agenda Rapat terhadap XSS
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('title') && is_string($this->title)) {
            $merge['title'] = trim(strip_tags($this->title));
        }
        if ($this->has('notes') && is_string($this->notes)) {
            $merge['notes'] = trim(strip_tags($this->notes));
        }
        if ($this->has('participants_count') && ! $this->has('participant_count')) {
            $merge['participant_count'] = $this->input('participants_count');
        }
        if ($this->has('stok') && ! $this->has('participant_count')) {
            $merge['participant_count'] = $this->input('stok');
        }
        if ($this->has('stock') && ! $this->has('participant_count')) {
            $merge['participant_count'] = $this->input('stock');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Audit 496 Tahap 11 (Param 11.8): Validasi isi file lampiran booking dengan mimetypes.
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'room_id' => $isUpdate ? ['sometimes', 'exists:rooms,id,deleted_at,NULL,is_active,1'] : ['required', 'exists:rooms,id,deleted_at,NULL,is_active,1'],
            'title' => $isUpdate ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'participant_count' => $isUpdate ? ['sometimes', 'integer', 'min:1', new WithinRoomCapacityRule] : ['required', 'integer', 'min:1', new WithinRoomCapacityRule],
            'participants_count' => ['sometimes', 'integer', 'min:1'],
            'stok' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'harga' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'saldo' => ['sometimes', 'numeric', 'min:0'],
            'start_time' => $isUpdate ? ['sometimes', 'date', 'after:now'] : ['required', 'date', 'after:now'],
            'end_time' => $isUpdate ? ['sometimes', 'date', 'after:start_time'] : ['required', 'date', 'after:start_time'],
            'notes' => ['nullable', 'string'],
            'attachment' => [
                'sometimes',
                'nullable',
                'file',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('start_time') || $validator->errors()->has('end_time')) {
                return;
            }

            $start = now()->parse($this->input('start_time'));
            $end = now()->parse($this->input('end_time'));

            if ($start->isWeekend() || $end->isWeekend() || ! $start->isSameDay($end)) {
                $validator->errors()->add('start_time', 'Booking hanya dapat dilakukan pada hari kerja dan tidak boleh melewati hari.');
            }

            (new WithinWorkingHoursRule)->validate('start_time', $start, fn (string $message) => $validator->errors()->add('start_time', $message));
            (new WithinWorkingHoursRule)->validate('end_time', $end, fn (string $message) => $validator->errors()->add('end_time', $message));
        });
    }
}
