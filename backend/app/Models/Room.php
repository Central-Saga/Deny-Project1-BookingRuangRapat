<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'capacity',
        'location',
        'facilities',
        'description',
        'is_active',
    ];

    /**
     * Audit 496 Parameter 5: Explicit Casts pada Model Room
     */
    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'facilities' => 'array',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity'  => 'integer',
            'facilities' => 'array',
        ];
    }

    /**
     * Audit 496 Parameter ROB (Robustness):
     * Validasi Invariant Bisnis di Domain Model.
     * Kapasitas ruangan wajib bernilai positif (minimal 1) dan tidak boleh nol atau negatif.
     */
    protected static function booted(): void
    {
        static::saving(function (Room $room): void {
            if ($room->capacity !== null && (int) $room->capacity < 1) {
                abort(422, 'Kapasitas ruangan harus minimal 1 orang dan tidak boleh negatif.');
            }
        });
    }

    public function setCapacityAttribute($value): void
    {
        if ($value !== null && (int) $value < 1) {
            abort(422, 'Kapasitas ruangan harus minimal 1 orang dan tidak boleh negatif.');
        }
        $this->attributes['capacity'] = $value !== null ? (int) $value : null;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
