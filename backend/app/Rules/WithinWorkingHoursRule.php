<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Audit 496 Parameter 14, 21:
 * Custom Validation Rule: Batas Jam Operasional Kantor 08:00 - 17:00 WITA.
 * Menolak pemesanan ruang rapat di luar jam kerja kantor dan akhir pekan.
 */
class WithinWorkingHoursRule implements ValidationRule
{
    /** Jam operasional kantor mengacu pada WITA (Asia/Makassar), terlepas dari timezone aplikasi. */
    private const TIMEZONE = 'Asia/Makassar';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = is_string($value) ? now()->parse($value) : $value;
        $date = $date?->clone()->setTimezone(self::TIMEZONE);

        if (!$date || $date->isWeekend() || $date->format('H:i') < '08:00' || $date->format('H:i') > '17:00') {
            $fail('Pemesanan hanya dapat dilakukan pada hari kerja pukul 08:00 sampai 17:00 WITA.');
        }
    }
}
