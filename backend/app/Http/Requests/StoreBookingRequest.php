<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Booking;
use Illuminate\Validation\Validator;
use App\Rules\WithinRoomCapacityRule;
use App\Rules\WithinWorkingHoursRule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id'    => ['required', 'exists:rooms,id,deleted_at,NULL,is_active,1'],
            'title'      => ['required', 'string', 'max:255'],
            'participant_count' => ['required', 'integer', 'min:1', new WithinRoomCapacityRule()],
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

            (new WithinWorkingHoursRule())->validate('start_time', $start, fn (string $message) => $validator->errors()->add('start_time', $message));
            (new WithinWorkingHoursRule())->validate('end_time', $end, fn (string $message) => $validator->errors()->add('end_time', $message));
        });
    }
}