<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_table_has_conflict_and_status_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('bookings'))->keyBy('name');

        $this->assertSame(['room_id', 'start_time', 'end_time'], $indexes['bookings_room_time_index']['columns']);
        $this->assertSame(['status'], $indexes['bookings_status_index']['columns']);
    }
}
