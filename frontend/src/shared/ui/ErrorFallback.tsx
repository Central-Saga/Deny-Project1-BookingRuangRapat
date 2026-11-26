import React from 'react';
import Link from 'next/link';

export interface ErrorFallbackProps {
    title?: string;
    description?: string;
    error?: Error & { digest?: string };
    reset?: () => void;
    homeHref?: string;
    homeLabel?: string;
    showDetails?: boolean;
    className?: string;
}

export const ErrorFallback: React.FC<ErrorFallbackProps> = ({
    title = 'Terjadi Kendala pada Sistem',
    description = 'Halaman mengalami kendala saat memuat atau memproses data. Silakan coba muat ulang atau kembali ke halaman utama.',
    error,
    reset,
    homeHref = '/',
    homeLabel = 'Kembali ke Beranda',
    showDetails = false,
    className = '',
}) => {
    return (
        <main
            role="alert"
            aria-live="assertive"
            className={`flex min-h-screen w-full items-center justify-center bg-[#072418] px-4 py-8 text-white selection:bg-[#f57c00] selection:text-slate-950 sm:px-6 ${className}`}
        >
            <div className="w-full max-w-lg overflow-hidden rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-8 shadow-2xl shadow-black/60 backdrop-blur-xl sm:p-10 text-center">
                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-500/15 text-rose-400 ring-1 ring-rose-500/30">
                    <svg aria-hidden="true" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>

                <div className="mb-3 inline-flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-rose-300">
                    <span className="h-2 w-2 rounded-full bg-rose-400 animate-pulse" />
                    Error Terdeteksi
                </div>

                <h1 className="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                    {title}
                </h1>

                <p className="mt-3 text-sm leading-relaxed text-emerald-100/75">
                    {description}
                </p>

                {(showDetails || error?.digest) && (
                    <div className="mt-5 rounded-xl border border-emerald-900 bg-emerald-950/60 p-3 text-left">
                        {error?.digest && (
                            <p className="text-[11px] font-mono text-emerald-300/80">
                                <span className="font-semibold text-emerald-200">Digest:</span> {error.digest}
                            </p>
                        )}
                        {showDetails && error?.message && (
                            <p className="mt-1 text-xs font-mono text-rose-300 truncate">
                                {error.message}
                            </p>
                        )}
                    </div>
                )}

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    {reset && (
                        <button
                            type="button"
                            onClick={() => reset()}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#f57c00] px-6 py-3 text-sm font-bold text-slate-950 shadow-lg shadow-orange-950/30 transition hover:bg-[#d96800] focus:outline-none focus:ring-4 focus:ring-[#f57c00]/30"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>Coba Lagi</span>
                        </button>
                    )}

                    <Link
                        href={homeHref}
                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-800 bg-emerald-900/30 px-6 py-3 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/60 hover:text-white focus:outline-none focus:ring-4 focus:ring-emerald-700/30"
                    >
                        <span>{homeLabel}</span>
                    </Link>
                </div>
            </div>
        </main>
    );
};
