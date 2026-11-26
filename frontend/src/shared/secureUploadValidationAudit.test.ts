import { describe, it, expect } from 'vitest';
import { DEFAULT_ACCEPT, DEFAULT_MAX_SIZE } from '@/components/FileUpload';
import { MAX_UPLOAD_FILE_SIZE, ACCEPTED_UPLOAD_MIMES, uploadFile } from '@/api';

describe('Secure Upload Validation & Guard Audit (#175)', () => {
    it('memastikan guard accept dan maxSize terdefinisi dengan aman (5 MB, format gambar aman)', () => {
        expect(DEFAULT_MAX_SIZE).toBe(5 * 1024 * 1024);
        expect(DEFAULT_ACCEPT).toBe('image/jpeg,image/png,image/webp');

        expect(MAX_UPLOAD_FILE_SIZE).toBe(5 * 1024 * 1024);
        expect(ACCEPTED_UPLOAD_MIMES).toContain('image/jpeg');
        expect(ACCEPTED_UPLOAD_MIMES).toContain('image/png');
        expect(ACCEPTED_UPLOAD_MIMES).toContain('image/webp');
    });

    it('menolak pengunggahan file yang melebihi maxSize pada sisi client sebelum dikirim', async () => {
        // Buat mock file berukuran 6 MB (6 * 1024 * 1024 bytes > 5 MB)
        const oversizedFile = new File(['x'.repeat(100)], 'huge-image.jpg', {
            type: 'image/jpeg',
        });
        Object.defineProperty(oversizedFile, 'size', {
            value: 6 * 1024 * 1024,
            configurable: true,
        });

        await expect(uploadFile(oversizedFile)).rejects.toThrow(/melebihi batas maksimal 5 MB/i);
    });

    it('menolak format file yang tidak diizinkan pada sisi client', async () => {
        // File skrip berbahaya .sh
        const scriptFile = new File(['#!/bin/bash echo hacked'], 'exploit.sh', {
            type: 'application/x-sh',
        });

        await expect(uploadFile(scriptFile)).rejects.toThrow(/tidak didukung/i);

        // File executable .exe
        const exeFile = new File(['MZ binary'], 'malware.exe', {
            type: 'application/x-msdownload',
        });

        await expect(uploadFile(exeFile)).rejects.toThrow(/tidak didukung/i);
    });

    it('mendukung onProgress callback selama proses pengunggahan', async () => {
        const validFile = new File(['dummy-image-content'], 'test-room.jpg', {
            type: 'image/jpeg',
        });
        Object.defineProperty(validFile, 'size', {
            value: 500 * 1024, // 500 KB
            configurable: true,
        });

        // Mock global fetch
        const progressValues: number[] = [];
        const originalFetch = global.fetch;

        global.fetch = async () => {
            return new Response(
                JSON.stringify({
                    success: true,
                    status: true,
                    message: 'File berhasil diunggah.',
                    data: {
                        url: 'http://localhost:8000/storage/uploads/test-room.jpg',
                        path: 'uploads/test-room.jpg',
                        temporary_url: 'http://localhost:8000/api/v1/uploads/download/test-room.jpg?signature=abc',
                        size: 500 * 1024,
                        mime_type: 'image/jpeg',
                        visibility: 'public',
                    },
                }),
                { status: 201, headers: { 'Content-Type': 'application/json' } }
            );
        };

        try {
            const result = await uploadFile(validFile, {
                onProgress: (p) => progressValues.push(p),
            });

            expect(result.url).toContain('uploads/test-room.jpg');
            expect(result.temporary_url).toContain('signature=abc');
            expect(progressValues).toContain(100);
            expect(progressValues.length).toBeGreaterThanOrEqual(3);
        } finally {
            global.fetch = originalFetch;
        }
    });
});
