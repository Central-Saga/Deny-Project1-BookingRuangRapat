<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 496 Parameter 3: Migrasi penambahan kolom deleted_at pada tabel rooms dan bookings
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms') && !Schema::hasColumn('rooms', 'deleted_at')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'deleted_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'deleted_at')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'deleted_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
