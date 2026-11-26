<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 496 Parameter 4:
 * Tambahkan Kolom capacity, location, dan facilities pada Tabel rooms
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            if (! Schema::hasColumn('rooms', 'capacity')) {
                $table->integer('capacity')->default(1);
            }

            if (! Schema::hasColumn('rooms', 'location')) {
                $table->string('location')->default('Belum ditentukan');
            }

            if (! Schema::hasColumn('rooms', 'facilities')) {
                $table->json('facilities')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            if (Schema::hasColumn('rooms', 'facilities')) {
                $table->dropColumn('facilities');
            }
            if (Schema::hasColumn('rooms', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('rooms', 'capacity')) {
                $table->dropColumn('capacity');
            }
        });
    }
};
