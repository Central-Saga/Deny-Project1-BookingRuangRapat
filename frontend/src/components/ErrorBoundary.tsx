'use client';

import React, { Component, ErrorInfo, ReactNode } from 'react';

export interface ErrorBoundaryProps {
    children?: ReactNode;
    fallback?: ReactNode | ((props: { error: Error; reset: () => void }) => ReactNode);
    onError?: (error: Error, errorInfo: ErrorInfo) => void;
    onReset?: () => void;
    title?: string;
    description?: string;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Client-Side Error Boundary Component untuk menangkap error pada subtree komponen
 * dan menampilkan fallback UI ramah (bukan blank screen).
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
        };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return {
            hasError: true,
            error,
        };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        if (this.props.onError) {
            this.props.onError(error, errorInfo);
        } else {
            console.error('ErrorBoundary caught an error:', error, errorInfo);
        }
    }

    reset = (): void => {
        if (this.props.onReset) {
            this.props.onReset();
        }
        this.setState({
            hasError: false,
            error: null,
        });
    };

    render(): ReactNode {
        if (this.state.hasError && this.state.error) {
            if (this.props.fallback) {
                if (typeof this.props.fallback === 'function') {
                    return this.props.fallback({
                        error: this.state.error,
                        reset: this.reset,
                    });
                }
                return this.props.fallback;
            }

            const title = this.props.title || 'Komponen Mengalami Kendala';
            const description = this.props.description || 'Terjadi kesalahan saat memuat bagian ini. Anda dapat mencoba memuat ulang komponen.';

            return (
                <div
                    role="alert"
                    aria-live="assertive"
                    data-testid="error-boundary-fallback"
                    className="my-4 rounded-2xl border border-rose-500/30 bg-[#0d2822] p-6 text-white shadow-xl"
                >
                    <div className="flex items-start gap-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-400">
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div className="flex-1 min-w-0">
                            <h3 className="text-base font-bold text-white">{title}</h3>
                            <p className="mt-1 text-xs leading-relaxed text-emerald-100/70">{description}</p>
                            {this.state.error.message && (
                                <p className="mt-2 rounded-lg bg-emerald-950/60 p-2 font-mono text-[11px] text-rose-300 truncate">
                                    {this.state.error.message}
                                </p>
                            )}
                            <div className="mt-4 flex gap-3">
                                <button
                                    type="button"
                                    onClick={this.reset}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#f57c00] px-4 py-2 text-xs font-bold text-slate-950 transition hover:bg-[#d96800]"
                                >
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    <span>Coba Lagi</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
