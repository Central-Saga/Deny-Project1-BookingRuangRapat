<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceStatusConstraint(['pending', 'approved', 'rejected', 'cancelled']);

        // Data lama: pembatalan pemohon tersimpan sebagai 'rejected' tanpa approval_notes.
        DB::table('bookings')
            ->where('status', 'rejected')
            ->whereNull('approval_notes')
            ->whereNotNull('cancellation_reason')
            ->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        DB::table('bookings')->where('status', 'cancelled')->update(['status' => 'rejected']);
        $this->replaceStatusConstraint(['pending', 'approved', 'rejected']);
    }

    private function replaceStatusConstraint(array $values): void
    {
        $list = implode(', ', array_map(fn (string $v) => "'{$v}'", $values));
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ({$list}))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending'");
        }
    }
};
