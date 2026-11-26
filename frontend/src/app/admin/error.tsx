'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Route Error Boundary untuk rute Administrator (/admin).
 */
export default function AdminError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Admin error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Kendala pada Panel Administrator"
            description="Gagal memuat antarmuka administrator atau data manajemen ruangan & pengguna. Silakan coba muat ulang."
            error={error}
            reset={reset}
            homeHref="/admin"
            homeLabel="Muat Ulang Panel Admin"
        />
    );
}
