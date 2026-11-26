'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Route Error Boundary untuk rute Riwayat Booking (/riwayat).
 */
export default function RiwayatError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Riwayat error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Kendala pada Riwayat Booking"
            description="Gagal memuat arsip dan riwayat booking ruangan rapat Anda. Silakan coba muat ulang."
            error={error}
            reset={reset}
            homeHref="/riwayat"
            homeLabel="Muat Ulang Riwayat"
        />
    );
}
