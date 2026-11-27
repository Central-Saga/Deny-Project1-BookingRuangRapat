import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { ErrorBoundary } from './ErrorBoundary';

describe('ErrorBoundary Component Unit Coverage', () => {
    it('merender children ketika tidak ada error', () => {
        const html = renderToStaticMarkup(
            <ErrorBoundary>
                <div>Children Content Berhasil Dimuat</div>
            </ErrorBoundary>
        );

        expect(html).toContain('Children Content Berhasil Dimuat');
    });

    it('menghasilkan state hasError saat getDerivedStateFromError dipanggil', () => {
        const mockError = new Error('Test Crash');
        const state = ErrorBoundary.getDerivedStateFromError(mockError);

        expect(state.hasError).toBe(true);
        expect(state.error).toBe(mockError);
    });

    it('merender fallback default dengan pesan error ketika terjadi error', () => {
        const boundary = new ErrorBoundary({
            title: 'Gagal Memuat Komponen',
            description: 'Silakan refresh halaman',
        });

        boundary.state = {
            hasError: true,
            error: new Error('Simulated Database Error'),
        };

        const rendered = boundary.render();
        const html = renderToStaticMarkup(rendered as React.ReactElement);

        expect(html).toContain('Gagal Memuat Komponen');
        expect(html).toContain('Silakan refresh halaman');
        expect(html).toContain('Simulated Database Error');
    });

    it('merender custom fallback node atau fungsi jika disediakan', () => {
        // Fallback static
        const staticBoundary = new ErrorBoundary({
            fallback: <div>Custom Static Fallback</div>,
        });
        staticBoundary.state = {
            hasError: true,
            error: new Error('Fail'),
        };
        const staticHtml = renderToStaticMarkup(staticBoundary.render() as React.ReactElement);
        expect(staticHtml).toContain('Custom Static Fallback');

        // Fallback function
        const fnBoundary = new ErrorBoundary({
            fallback: ({ error, reset }) => (
                <div>
                    <span>Function Fallback: {error.message}</span>
                    <button onClick={reset}>Reset Now</button>
                </div>
            ),
        });
        fnBoundary.state = {
            hasError: true,
            error: new Error('Function Error Detail'),
        };
        const fnHtml = renderToStaticMarkup(fnBoundary.render() as React.ReactElement);
        expect(fnHtml).toContain('Function Fallback: Function Error Detail');
        expect(fnHtml).toContain('Reset Now');
    });

    it('memanggil onError callback saat componentDidCatch dieksekusi', () => {
        const onError = vi.fn();
        const boundary = new ErrorBoundary({ onError });
        const error = new Error('Caught Error');
        const errorInfo = { componentStack: 'stack trace' };

        boundary.componentDidCatch(error, errorInfo);
        expect(onError).toHaveBeenCalledWith(error, errorInfo);
    });

    it('memanggil onReset dan mereset state saat method reset() dipanggil', () => {
        const onReset = vi.fn();
        const boundary = new ErrorBoundary({ onReset });
        boundary.state = {
            hasError: true,
            error: new Error('Something broke'),
        };

        let updatedState: unknown = null;
        boundary.setState = vi.fn((newState) => {
            updatedState = newState;
        }) as unknown as typeof boundary.setState;

        boundary.reset();
        expect(onReset).toHaveBeenCalled();
        expect(updatedState).toEqual({
            hasError: false,
            error: null,
        });
    });
});
