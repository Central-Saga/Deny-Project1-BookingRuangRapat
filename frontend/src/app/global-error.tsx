'use client';

import { useEffect } from 'react';
import { ErrorFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Global Error Boundary untuk menangani runtime exception pada root layout level.
 * Next.js App Router: Global error boundary harus mendefinisikan elemen <html> dan <body> sendiri.
 */
export default function GlobalError({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error('Global error boundary caught an error:', error);
    }, [error]);

    return (
        <html lang="id">
            <body className="min-h-screen bg-[#072418] text-white">
                <ErrorFallback
                    title="Terjadi Kendala Kritis Sistem"
                    description="Komponen utama sistem mengalami kendala. Silakan muat ulang halaman atau kembali ke beranda."
                    error={error}
                    reset={reset}
                    homeHref="/"
                    homeLabel="Kembali ke Beranda"
                />
            </body>
        </html>
    );
}
