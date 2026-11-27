<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Model Audit Trail ActivityLog (Audit 496 Parameter SEC - Issue #178).
 * Tercatat & Tahan Hapus: Log audit bersifat immutable dan dicegah dari penghapusan.
 */
class ActivityLog extends SpatieActivity
{
    protected static function booted(): void
    {
        static::deleting(function ($model): void {
            abort(403, 'Audit log bersifat immutable dan tahan hapus.');
        });
    }
}
