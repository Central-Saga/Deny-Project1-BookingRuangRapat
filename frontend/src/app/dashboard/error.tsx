'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Route Error Boundary untuk rute Dashboard Karyawan (/dashboard).
 */
export default function DashboardError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Dashboard error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Kendala pada Dashboard Karyawan"
            description="Gagal memuat atau memproses data agenda rapat karyawan. Silakan coba muat ulang sesi dashboard Anda."
            error={error}
            reset={reset}
            homeHref="/dashboard"
            homeLabel="Muat Ulang Dashboard"
        />
    );
}
