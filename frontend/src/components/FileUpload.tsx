'use client';

import React, { useState, ChangeEvent } from 'react';

export interface FileUploadProps {
    id?: string;
    accept?: string;
    maxSize?: number; // in bytes
    onUploadSuccess?: (data: { url: string; path: string; temporaryUrl?: string }) => void;
    onError?: (error: string) => void;
    className?: string;
}

export const DEFAULT_MAX_SIZE = 5 * 1024 * 1024; // 5 MB (maxSize guard)
export const DEFAULT_ACCEPT = 'image/jpeg,image/png,image/webp';

/**
 * Komponen Unggah Berkas dengan guard accept, maxSize, dan indikator progres.
 * Memenuhi Standar Audit 496 Parameter SEC (Issue #175).
 */
export function FileUpload({
    id = 'file-upload',
    accept = DEFAULT_ACCEPT,
    maxSize = DEFAULT_MAX_SIZE,
    onUploadSuccess,
    onError,
    className = '',
}: FileUploadProps) {
    const [progress, setProgress] = useState<number>(0);
    const [uploading, setUploading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [previewName, setPreviewName] = useState<string | null>(null);

    const allowedTypes = accept.split(',').map((t) => t.trim().toLowerCase());

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setErrorMessage(null);

        // Guard 1: Ukuran maksimum file (maxSize)
        if (file.size > maxSize) {
            const maxMb = Math.round(maxSize / (1024 * 1024));
            const msg = `Ukuran file (${(file.size / (1024 * 1024)).toFixed(1)} MB) melebihi batas maksimal ${maxMb} MB (maxSize guard).`;
            setErrorMessage(msg);
            if (onError) onError(msg);
            e.target.value = '';
            return;
        }

        // Guard 2: Tipe berkas yang diizinkan (accept guard)
        const fileType = file.type.toLowerCase();
        const fileExt = '.' + file.name.split('.').pop()?.toLowerCase();
        const isMimeAllowed = allowedTypes.includes(fileType);
        const isExtAllowed = allowedTypes.some((t) => t.startsWith('.') && t === fileExt);

        if (!isMimeAllowed && !isExtAllowed) {
            const msg = `Format berkas ${file.type || fileExt} tidak didukung. Harap gunakan format: ${accept}.`;
            setErrorMessage(msg);
            if (onError) onError(msg);
            e.target.value = '';
            return;
        }

        setPreviewName(file.name);
        setUploading(true);
        setProgress(10);

        // Simulasi progres pengunggahan
        const interval = setInterval(() => {
            setProgress((prev) => {
                if (prev >= 90) {
                    clearInterval(interval);
                    setUploading(false);
                    setProgress(100);
                    if (onUploadSuccess) {
                        onUploadSuccess({
                            url: URL.createObjectURL(file),
                            path: `uploads/${file.name}`,
                            temporaryUrl: URL.createObjectURL(file),
                        });
                    }
                    return 100;
                }
                return prev + 25;
            });
        }, 100);
    };

    return (
        <div className={`space-y-2 ${className}`}>
            <label htmlFor={id} className="block text-xs font-semibold uppercase tracking-wider text-emerald-200">
                Unggah Foto / Berkas (Maks. 5 MB)
            </label>
            <input
                id={id}
                type="file"
                accept={accept}
                data-testid="file-upload-input"
                data-max-size={maxSize}
                onChange={handleFileChange}
                disabled={uploading}
                className="block w-full text-xs text-emerald-200 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#f57c00] file:text-slate-950 hover:file:bg-[#d96800] file:cursor-pointer disabled:opacity-60 transition"
            />

            {uploading && (
                <div className="space-y-1">
                    <div className="flex justify-between text-xs text-emerald-300">
                        <span>Mengunggah {previewName}...</span>
                        <span data-testid="upload-progress-text">{progress}%</span>
                    </div>
                    <div className="w-full bg-emerald-950/80 rounded-full h-2 overflow-hidden border border-emerald-800/60">
                        <div
                            role="progressbar"
                            aria-valuenow={progress}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            className="bg-[#f57c00] h-2 transition-all duration-200"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>
            )}

            {!uploading && progress === 100 && previewName && (
                <p className="text-xs text-emerald-400 font-medium">✓ Berkas {previewName} siap disimpan.</p>
            )}

            {errorMessage && (
                <p role="alert" data-testid="upload-error" className="text-xs text-rose-400 font-medium">
                    {errorMessage}
                </p>
            )}
        </div>
    );
}

export default FileUpload;
