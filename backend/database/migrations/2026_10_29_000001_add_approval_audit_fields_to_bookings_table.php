<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (!Schema::hasColumn('bookings', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('approval_notes')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bookings', 'decided_at')) {
                $table->timestamp('decided_at')
                    ->nullable()
                    ->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('bookings', 'decided_at')) {
                $table->dropColumn('decided_at');
            }
        });
    }
};

