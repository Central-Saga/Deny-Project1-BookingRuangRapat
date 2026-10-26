<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Spatie permission dihapus; kolom users.role adalah satu-satunya sumber otorisasi.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Tabel Spatie sengaja tidak dibuat ulang.
    }
};
