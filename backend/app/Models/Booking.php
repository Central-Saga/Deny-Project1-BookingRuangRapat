<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

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
        'end_time'   => 'datetime',
        'created_at' => 'datetime',
        'decided_at' => 'datetime',
        'participant_count' => 'integer',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time'   => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

