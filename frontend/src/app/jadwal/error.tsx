'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Route Error Boundary untuk rute Kalender Jadwal (/jadwal).
 */
export default function JadwalError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Jadwal error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Kendala pada Kalender Jadwal"
            description="Gagal memuat visual kalender jadwal booking ruangan. Silakan coba muat ulang jadwal."
            error={error}
            reset={reset}
            homeHref="/jadwal"
            homeLabel="Muat Ulang Jadwal"
        />
    );
}
