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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity'  => 'integer',
            'facilities' => 'array',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
// Cast facilities to array for 65-parameter standard