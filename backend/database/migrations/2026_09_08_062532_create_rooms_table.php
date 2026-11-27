<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rooms');
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('capacity')->default(1);
            $table->string('location')->default('Belum ditentukan');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['name', 'deleted_at'], 'rooms_name_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
