import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    Input,
    Modal,
    Spinner,
    ErrorFallback,
    NotFoundFallback,
} from './index';

describe('Shared UI Presentational Components Unit Coverage', () => {
    describe('Badge', () => {
        it('renders with default variant and children', () => {
            const html = renderToStaticMarkup(<Badge>Default Badge</Badge>);
            expect(html).toContain('Default Badge');
            expect(html).toContain('bg-gray-100');
        });

        it('renders various variants correctly', () => {
            const variants = ['success', 'warning', 'danger', 'outline', 'info', 'neutral'] as const;
            for (const v of variants) {
                const html = renderToStaticMarkup(<Badge variant={v}>{v}</Badge>);
                expect(html).toContain(v);
            }
        });
    });

    describe('Button', () => {
        it('renders standard button with children', () => {
            const html = renderToStaticMarkup(<Button>Click Me</Button>);
            expect(html).toContain('Click Me');
            expect(html).toContain('<button');
        });

        it('renders variants and sizes', () => {
            const variants = ['primary', 'secondary', 'danger', 'outline', 'ghost'] as const;
            const sizes = ['sm', 'md', 'lg'] as const;

            for (const variant of variants) {
                for (const size of sizes) {
                    const html = renderToStaticMarkup(
                        <Button variant={variant} size={size}>
                            {variant}-{size}
                        </Button>
                    );
                    expect(html).toContain(`${variant}-${size}`);
                }
            }
        });

        it('renders loading state with spinner and disabled attribute', () => {
            const html = renderToStaticMarkup(<Button isLoading>Submit</Button>);
            expect(html).toContain('disabled');
            expect(html).toContain('animate-spin');
        });
    });

    describe('Card', () => {
        it('renders complete card composite elements', () => {
            const html = renderToStaticMarkup(
                <Card className="custom-card">
                    <CardHeader>
                        <CardTitle>Test Card Title</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p>Main content area</p>
                    </CardContent>
                </Card>
            );

            expect(html).toContain('Test Card Title');
            expect(html).toContain('Main content area');
            expect(html).toContain('custom-card');
        });
    });

    describe('Input', () => {
        it('renders input with label, placeholder, and helperText', () => {
            const html = renderToStaticMarkup(
                <Input
                    label="Nama Lengkap"
                    id="nama"
                    placeholder="Masukkan nama"
                    helperText="Sesuai KTP"
                />
            );

            expect(html).toContain('Nama Lengkap');
            expect(html).toContain('Masukkan nama');
            expect(html).toContain('Sesuai KTP');
        });

        it('renders error state properly', () => {
            const html = renderToStaticMarkup(
                <Input
                    label="Email"
                    id="email"
                    error="Email tidak valid"
                />
            );

            expect(html).toContain('Email tidak valid');
            expect(html).toContain('border-red-500');
        });

        it('handles disabled attribute', () => {
            const html = renderToStaticMarkup(
                <Input id="test" disabled />
            );

            expect(html).toContain('disabled');
        });
    });

    describe('Modal', () => {
        it('renders null when isOpen is false', () => {
            const html = renderToStaticMarkup(
                <Modal isOpen={false} onClose={() => {}}>
                    <p>Hidden Modal</p>
                </Modal>
            );

            expect(html).toBe('');
        });

        it('renders modal dialog content when isOpen is true', () => {
            const html = renderToStaticMarkup(
                <Modal
                    isOpen={true}
                    onClose={() => {}}
                    title="Modal Konfirmasi"
                >
                    <p>Konten Dialog</p>
                </Modal>
            );

            expect(html).toContain('Modal Konfirmasi');
            expect(html).toContain('Konten Dialog');
        });
    });

    describe('Spinner', () => {
        it('renders spinner with different sizes', () => {
            const sizes = ['sm', 'md', 'lg'] as const;
            for (const size of sizes) {
                const html = renderToStaticMarkup(<Spinner size={size} />);
                expect(html).toContain('animate-spin');
            }
        });
    });

    describe('ErrorFallback', () => {
        it('renders error fallback with message and retry button', () => {
            const onReset = vi.fn();
            const html = renderToStaticMarkup(
                <ErrorFallback
                    title="Koneksi Terputus"
                    description="Gagal memuat data jadwal"
                    reset={onReset}
                />
            );

            expect(html).toContain('Koneksi Terputus');
            expect(html).toContain('Gagal memuat data jadwal');
            expect(html).toContain('Coba Lagi');
        });

        it('renders error details when showDetails is true', () => {
            const testError = new Error('Server 500 error');
            const html = renderToStaticMarkup(
                <ErrorFallback
                    error={testError}
                    showDetails={true}
                />
            );

            expect(html).toContain('Server 500 error');
        });
    });

    describe('NotFoundFallback', () => {
        it('renders not found fallback with custom title and link', () => {
            const html = renderToStaticMarkup(
                <NotFoundFallback
                    title="Halaman Tidak Ditemukan"
                    description="Ruangan yang Anda cari tidak tersedia."
                    homeHref="/ruangan"
                />
            );

            expect(html).toContain('Halaman Tidak Ditemukan');
            expect(html).toContain('Ruangan yang Anda cari tidak tersedia.');
            expect(html).toContain('href="/ruangan"');
        });
    });
});
