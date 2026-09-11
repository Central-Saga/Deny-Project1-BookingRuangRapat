<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id'    => ['required', 'exists:rooms,id'],
            'title'      => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time'   => ['required', 'date', 'after:start_time'],
            'notes'      => ['nullable', 'string'],
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

            if ($start->isWeekend() || $end->isWeekend() || !$start->isSameDay($end)) {
                $validator->errors()->add('start_time', 'Booking hanya dapat dilakukan pada hari kerja dan tidak boleh melewati hari.');
            }

            if ($start->format('H:i') < '08:00' || $end->format('H:i') > '17:00') {
                $validator->errors()->add('start_time', 'Booking hanya dapat dilakukan antara pukul 08:00 dan 17:00.');
            }
        });
    }
}