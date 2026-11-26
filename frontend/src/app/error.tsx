'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Root Error Boundary untuk menangani runtime exception pada seluruh halaman.
 * Ambang batas: bukan blank (menampilkan Fallback UI ramah pengguna).
 */
export default function RootError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        // Log error ke console untuk observabilitas internal
        console.error('Root error boundary caught an error:', error);
    }, [error]);

    return (
        <ErrorFallback
            title="Terjadi Kendala pada Halaman"
            description="Aplikasi mengalami kendala tak terduga saat memuat data atau merender antarmuka. Anda dapat mencoba memuat ulang."
            error={error}
            reset={reset}
            homeHref="/"
            homeLabel="Kembali ke Beranda"
        />
    );
}
