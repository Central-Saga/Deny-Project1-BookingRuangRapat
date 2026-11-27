import { describe, it, expect } from 'vitest';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { FileUpload, DEFAULT_MAX_SIZE, DEFAULT_ACCEPT } from './FileUpload';

describe('FileUpload Component Unit Coverage', () => {
    it('memastikan konstanta batas ukuran dan tipe berkas sesuai standar', () => {
        expect(DEFAULT_MAX_SIZE).toBe(5 * 1024 * 1024);
        expect(DEFAULT_ACCEPT).toBe('image/jpeg,image/png,image/webp');
    });

    it('merender elemen input file dengan atribut guard accept yang benar', () => {
        const html = renderToStaticMarkup(
            <FileUpload
                id="avatar-upload"
                accept="image/png,image/jpeg"
                maxSize={2 * 1024 * 1024}
                className="custom-uploader"
            />
        );

        expect(html).toContain('id="avatar-upload"');
        expect(html).toContain('accept="image/png,image/jpeg"');
        expect(html).toContain('type="file"');
        expect(html).toContain('Unggah Foto / Berkas');
    });
});
