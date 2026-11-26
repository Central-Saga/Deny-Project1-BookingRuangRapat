'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Route Error Boundary untuk rute Ruangan Tersedia (/ruangan).
 */
export default function RuanganError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Ruangan error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Kendala pada Inventaris Ruangan"
            description="Gagal memuat status ruangan atau timeline ketersediaan rapat. Silakan muat ulang halaman."
            error={error}
            reset={reset}
            homeHref="/ruangan"
            homeLabel="Muat Ulang Ruangan"
        />
    );
}
