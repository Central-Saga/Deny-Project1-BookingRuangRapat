<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property int $capacity
 * @property string $location
 * @property array|null $facilities
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Room extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('room')
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Room {$eventName}");
    }

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
            'capacity' => 'integer',
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

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
