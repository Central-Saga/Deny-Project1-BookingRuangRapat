<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $user_id
 * @property int $room_id
 * @property string $title
 * @property int $participant_count
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property string $status
 * @property string|null $notes
 * @property string|null $cancellation_reason
 * @property string|null $approval_notes
 * @property int|null $approved_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Room $room
 * @property-read User|null $approver
 */
class Booking extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('booking')
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Booking {$eventName}");
    }

    protected $fillable = [
        'user_id',
        'room_id',
        'title',
        'participant_count',
        'start_time',
        'end_time',
        'status',
        'notes',
        'cancellation_reason',
        'approval_notes',
        'approved_by',
        'decided_at',
    ];

    /**
     * Audit 496 Parameter 5: Explicit Casts pada Model Booking
     * start_time => 'datetime', end_time => 'datetime', created_at => 'datetime'
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'created_at' => 'datetime',
        'decided_at' => 'datetime',
        'participant_count' => 'integer',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'created_at' => 'datetime',
            'decided_at' => 'datetime',
            'participant_count' => 'integer',
        ];
    }

    /**
     * Audit 496 Parameter ROB (Robustness):
     * Validasi Invariant Bisnis di Domain Model.
     * Jumlah peserta rapat wajib bernilai positif (minimal 1) dan tidak boleh nol atau negatif.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            if ($booking->participant_count !== null && (int) $booking->participant_count < 1) {
                abort(422, 'Jumlah peserta rapat harus minimal 1 orang dan tidak boleh negatif.');
            }
        });
    }

    public function setParticipantCountAttribute($value): void
    {
        if ($value !== null && (int) $value < 1) {
            abort(422, 'Jumlah peserta rapat harus minimal 1 orang dan tidak boleh negatif.');
        }
        $this->attributes['participant_count'] = $value !== null ? (int) $value : null;
    }

    /**
     * Audit 496 Parameter 51: Sanitasi Input Judul & Agenda Rapat terhadap XSS
     * Membersihkan tag HTML (strip_tags) pada judul, agenda/notes, dan teks input sebelum disimpan.
     */
    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? trim(strip_tags($value)) : null,
        );
    }

    protected function notes(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? trim(strip_tags($value)) : null,
        );
    }

    protected function cancellationReason(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? trim(strip_tags($value)) : null,
        );
    }

    protected function approvalNotes(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? trim(strip_tags($value)) : null,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
