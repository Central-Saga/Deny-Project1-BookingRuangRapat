import React from 'react';
import Link from 'next/link';

export interface NotFoundFallbackProps {
    title?: string;
    description?: string;
    homeHref?: string;
    homeLabel?: string;
    className?: string;
}

export const NotFoundFallback: React.FC<NotFoundFallbackProps> = ({
    title = 'Halaman Tidak Ditemukan',
    description = 'Halaman atau alamat yang Anda tuju tidak tersedia, telah dipindahkan, atau Anda belum memiliki hak akses.',
    homeHref = '/',
    homeLabel = 'Kembali ke Beranda',
    className = '',
}) => {
    return (
        <main
            role="main"
            className={`flex min-h-screen w-full items-center justify-center bg-[#072418] px-4 py-8 text-white selection:bg-[#f57c00] selection:text-slate-950 sm:px-6 ${className}`}
        >
            <div className="w-full max-w-lg overflow-hidden rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-8 shadow-2xl shadow-black/60 backdrop-blur-xl sm:p-10 text-center">
                <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-amber-500/15 text-[#f59e0b] ring-1 ring-amber-500/30">
                    <span className="text-3xl font-black tracking-tighter">404</span>
                </div>

                <div className="mb-3 inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-300">
                    <span className="h-2 w-2 rounded-full bg-[#f59e0b]" />
                    Status 404
                </div>

                <h1 className="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                    {title}
                </h1>

                <p className="mt-3 text-sm leading-relaxed text-emerald-100/75">
                    {description}
                </p>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Link
                        href={homeHref}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#f57c00] px-6 py-3 text-sm font-bold text-slate-950 shadow-lg shadow-orange-950/30 transition hover:bg-[#d96800] focus:outline-none focus:ring-4 focus:ring-[#f57c00]/30"
                    >
                        <span>{homeLabel}</span>
                    </Link>

                    <Link
                        href="/dashboard"
                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-800 bg-emerald-900/30 px-6 py-3 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/60 hover:text-white focus:outline-none focus:ring-4 focus:ring-emerald-700/30"
                    >
                        <span>Buka Dashboard</span>
                    </Link>
                </div>
            </div>
        </main>
    );
};
