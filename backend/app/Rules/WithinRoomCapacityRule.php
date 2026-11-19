<?php

namespace App\Rules;

use App\Models\Room;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Audit 496 Parameter 14, 21:
 * Custom Validation Rule: Jumlah Peserta Tidak Melebihi Kapasitas Ruangan.
 * Memvalidasi kolom participant_count tidak melebihi capacity ruangan terpilih.
 */
class WithinRoomCapacityRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $roomId = request()->input('room_id');
        $room = $roomId ? Room::whereKey($roomId)->where('is_active', true)->first() : null;

        if (!$room || (int) $value > $room->capacity) {
            $fail('Jumlah peserta melebihi kapasitas ruangan yang dipilih.');
        }
    }
}
