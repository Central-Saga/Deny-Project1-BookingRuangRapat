/**
 * Centralized API Client Module (Audit 496 - Issue #164).
 * Seluruh pemanggilan network `fetch` terpusat di dalam modul ini (0 di luar api/).
 */

import {
    clearApiCache,
    finalizeInFlightRequest,
    getApiCache,
    getOrCreateAbortSignal,
    invalidateApiCache,
    setApiCache,
} from './cache';

export const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api').replace(/\/$/, '');
export const TOKEN_KEY = 'meetspace_auth_token';

let inMemoryToken: string | null = null;

export function getToken(): string | null {
    if (typeof window !== 'undefined') {
        try {
            const localToken = window.localStorage.getItem(TOKEN_KEY);
            if (localToken) {
                return localToken;
            }
        } catch {
            // ignore
        }
    }
    if (typeof document !== 'undefined') {
        const match = document.cookie.match(new RegExp('(?:^|;\\s*)(?:' + TOKEN_KEY + '|auth_token|token)=([^;]*)'));
        if (match && match[1]) {
            return decodeURIComponent(match[1]);
        }
    }
    return inMemoryToken;
}

export function hasToken(): boolean {
    return Boolean(getToken());
}

export function saveToken(token: string): void {
    inMemoryToken = token;
    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(TOKEN_KEY, token);
        } catch {
            // ignore
        }
    }
    if (typeof document !== 'undefined') {
        document.cookie = `${TOKEN_KEY}=${encodeURIComponent(token)}; path=/; SameSite=Lax`;
        document.cookie = `auth_token=${encodeURIComponent(token)}; path=/; SameSite=Lax`;
    }
}

export function clearToken(): void {
    inMemoryToken = null;
    if (typeof window !== 'undefined') {
        try {
            window.localStorage.removeItem(TOKEN_KEY);
        } catch {
            // ignore
        }
    }
    if (typeof document !== 'undefined') {
        document.cookie = `${TOKEN_KEY}=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax`;
        document.cookie = `auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax`;
    }
    clearApiCache();
}

export function authHeaders(): Record<string, string> {
    const token = getToken();
    return token ? { Accept: 'application/json', Authorization: `Bearer ${token}` } : { Accept: 'application/json' };
}

export interface ApiErrorItem {
    field?: string;
    message?: string;
}

export interface ApiErrorEnvelope {
    success?: boolean;
    status?: boolean;
    message?: string;
    data?: unknown;
    errors?: ApiErrorItem[] | Record<string, string[] | string>;
}

export interface ApiErrorOptions {
    message: string;
    status: number;
    fieldErrors?: Record<string, string>;
    details?: string[];
    rawErrors?: unknown;
}

export class ApiError extends Error {
    public status: number;
    public fieldErrors: Record<string, string>;
    public details: string[];
    public rawErrors: unknown;

    constructor(
        messageOrOptions: string | ApiErrorOptions,
        status?: number,
        fieldErrors: Record<string, string> = {},
        details: string[] = [],
        rawErrors: unknown = null
    ) {
        if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
            super(messageOrOptions.message);
            this.status = messageOrOptions.status;
            this.fieldErrors = messageOrOptions.fieldErrors || (messageOrOptions.rawErrors ? extractFieldErrors(messageOrOptions.rawErrors) : {});
            this.details = messageOrOptions.details || [];
            this.rawErrors = messageOrOptions.rawErrors ?? null;
        } else {
            super(messageOrOptions);
            this.status = status ?? 500;
            this.fieldErrors = fieldErrors;
            this.details = details;
            this.rawErrors = rawErrors;
        }
        this.name = 'ApiError';
        Object.setPrototypeOf(this, ApiError.prototype);
    }
}

export function extractFieldErrors(errors: unknown): Record<string, string> {
    const result: Record<string, string> = {};
    if (!errors) return result;

    if (Array.isArray(errors)) {
        for (const item of errors) {
            if (item && typeof item === 'object') {
                const field = 'field' in item && typeof item.field === 'string' ? item.field.trim() : '';
                const message = 'message' in item && typeof item.message === 'string' ? item.message.trim() : '';
                if (field && message) {
                    result[field] = message;
                    // Bidirectional alias normalization
                    if (field === 'room_id' || field === 'room') {
                        result['room_id'] = message;
                        result['room'] = message;
                    }
                    if (field === 'participant_count' || field === 'participants') {
                        result['participant_count'] = message;
                        result['participants'] = message;
                    }
                    if (field === 'title' || field === 'name') {
                        result['title'] = message;
                        result['name'] = message;
                    }
                    if (field === 'start_time' && (message.toLowerCase().includes('hari') || message.toLowerCase().includes('tanggal'))) {
                        result['date'] = message;
                    }
                }
            }
        }
    } else if (typeof errors === 'object' && errors !== null) {
        for (const [key, val] of Object.entries(errors)) {
            let msg = '';
            if (Array.isArray(val) && val.length > 0 && typeof val[0] === 'string') {
                msg = val[0].trim();
            } else if (typeof val === 'string') {
                msg = val.trim();
            }
            if (msg) {
                result[key] = msg;
                // Bidirectional alias normalization
                if (key === 'room_id' || key === 'room') {
                    result['room_id'] = msg;
                    result['room'] = msg;
                }
                if (key === 'participant_count' || key === 'participants') {
                    result['participant_count'] = msg;
                    result['participants'] = msg;
                }
                if (key === 'title' || key === 'name') {
                    result['title'] = msg;
                    result['name'] = msg;
                }
                if (key === 'start_time' && (msg.toLowerCase().includes('hari') || msg.toLowerCase().includes('tanggal'))) {
                    result['date'] = msg;
                }
            }
        }
    }

    return result;
}

function extractErrorDetails(errors: ApiErrorEnvelope['errors']): string[] {
    if (!errors) return [];
    if (Array.isArray(errors)) {
        return errors
            .map((item) => (typeof item?.message === 'string' ? item.message.trim() : ''))
            .filter(Boolean);
    }
    if (typeof errors === 'object') {
        return Object.values(errors)
            .flatMap((entry) => (Array.isArray(entry) ? entry : [entry]))
            .map((msg) => (typeof msg === 'string' ? msg.trim() : ''))
            .filter(Boolean);
    }
    return [];
}

export async function getErrorMessage(response: Response, fallback: string): Promise<string> {
    let data: ApiErrorEnvelope | null = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }
    const message = typeof data?.message === 'string' ? data.message.trim() : '';
    const details = extractErrorDetails(data?.errors);

    if (message && details.length > 0) {
        return `${message} (${details.join(', ')})`;
    }
    if (message) {
        return message;
    }
    if (details.length > 0) {
        return details.join(', ');
    }
    return fallback;
}

export interface RequestOptions extends RequestInit {
    skipAuth?: boolean;
    useCache?: boolean;
    cacheTag?: string;
    cacheTtlMs?: number;
    preventRace?: boolean;
}

function detectTagFromPath(path: string): string | undefined {
    if (path.includes('room')) return 'rooms';
    if (path.includes('booking')) return 'bookings';
    if (path.includes('user')) return 'users';
    if (path.includes('categor')) return 'categories';
    return undefined;
}

/**
 * Satu-satunya modul client terpusat untuk semua HTTP network request (0 di luar api/).
 * Menyediakan:
 * 1. Otomatisasi header autentikasi & error envelope parsing.
 * 2. In-memory caching pada GET request untuk performa.
 * 3. Invalidasi cache seketika setelah mutasi (POST, PUT, PATCH, DELETE).
 * 4. Pencegahan race condition dengan pembatalan request berurutan yang usang.
 */
export async function apiClient<T = unknown>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    const {
        skipAuth = false,
        useCache = false,
        cacheTag,
        cacheTtlMs = 60000,
        preventRace = false,
        headers = {},
        ...restOptions
    } = options;

    const url = endpoint.startsWith('http') ? endpoint : `${API_URL}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;
    const method = (restOptions.method || 'GET').toUpperCase();
    const tag = cacheTag || detectTagFromPath(endpoint);

    // 1. Cek Cache untuk request GET
    const cacheKey = `${method}:${url}`;
    if (method === 'GET' && useCache) {
        const cached = getApiCache<T>(cacheKey);
        if (cached !== null) {
            return cached;
        }
    }

    // 2. Proteksi Race Condition
    let signal = restOptions.signal;
    if (preventRace && method === 'GET') {
        signal = getOrCreateAbortSignal(cacheKey);
    }

    // 3. Gabungkan Headers
    const extraHeaders: Record<string, string> = {};
    if (headers instanceof Headers) {
        headers.forEach((value, key) => {
            extraHeaders[key] = value;
        });
    } else if (Array.isArray(headers)) {
        headers.forEach(([key, value]) => {
            extraHeaders[key] = value;
        });
    } else if (headers && typeof headers === 'object') {
        Object.assign(extraHeaders, headers);
    }

    const requestHeaders: Record<string, string> = {
        Accept: 'application/json',
        ...(skipAuth ? {} : authHeaders()),
        ...extraHeaders,
    };

    let response: Response;
    try {
        response = await fetch(url, {
            ...restOptions,
            headers: requestHeaders,
            signal,
        });
    } catch (err: unknown) {
        if (err instanceof Error && err.name === 'AbortError') {
            throw err; // Lempar abort error tanpa fallback koneksi
        }
        throw new Error('Tidak dapat terhubung ke server API. Periksa koneksi dan coba lagi.');
    } finally {
        if (preventRace && method === 'GET') {
            finalizeInFlightRequest(cacheKey);
        }
    }

    // 4. Penanganan 401 Unauthorized
    if (!skipAuth && response.status === 401) {
        clearToken();
        if (typeof window !== 'undefined') {
            window.location.replace('/');
        }
        throw new ApiError('Sesi login berakhir. Silakan masuk kembali.', 401);
    }

    if (!response.ok) {
        let envelope: ApiErrorEnvelope | null = null;
        try {
            envelope = await response.json();
        } catch {
            envelope = null;
        }

        const details = extractErrorDetails(envelope?.errors);
        const fieldErrors = extractFieldErrors(envelope?.errors);
        let message = typeof envelope?.message === 'string' && envelope.message.trim()
            ? envelope.message.trim()
            : `Permintaan gagal dengan status ${response.status}`;

        if (details.length > 0 && !message.includes(details[0])) {
            message = `${message} (${details.join(', ')})`;
        }

        throw new ApiError(message, response.status, fieldErrors, details, envelope?.errors);
    }

    if (response.status === 204) {
        // No Content
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            invalidateApiCache(tag);
        }
        return JSON.parse('null');
    }

    const data: T = await response.json();

    // 5. Invalidasi cache otomatis setelah mutasi (POST, PUT, PATCH, DELETE)
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        invalidateApiCache(tag);
    } else if (method === 'GET' && useCache) {
        setApiCache(cacheKey, data, cacheTtlMs, tag);
    }

    return data;
}

export type PageResponse<T> = {
    data: T[];
    meta?: { current_page?: number; last_page?: number; per_page?: number; total?: number };
};

export async function getAllPages<T>(path: string, fallback: string): Promise<T[]> {
    const items: T[] = [];
    let page = 1;
    let lastPage = 1;

    do {
        const separator = path.includes('?') ? '&' : '?';
        const endpoint = `${path}${separator}page=${page}&per_page=100`;
        const payload = await apiClient<PageResponse<T>>(endpoint, {
            useCache: true,
            preventRace: true,
        }).catch((err) => {
            throw new Error(err.message || fallback);
        });

        if (!payload || !Array.isArray(payload.data)) {
            throw new Error('Struktur respons daftar API tidak valid.');
        }
        items.push(...payload.data);

        const reportedPage = payload.meta?.current_page ?? page;
        const totalPages = payload.meta?.last_page ?? 1;
        if (reportedPage !== page || !Number.isSafeInteger(totalPages) || totalPages < 1 || totalPages > 100) {
            throw new Error('Metadata pagination API tidak valid atau terlalu besar.');
        }
        lastPage = totalPages;
        page++;
    } while (page <= lastPage);

    return items;
}

export const MAX_UPLOAD_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
export const ACCEPTED_UPLOAD_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

export interface UploadResponse {
    url: string;
    temporary_url?: string;
    temporaryUrl?: string;
    signed_url?: string;
    path: string;
    size: number;
    mime_type: string;
    visibility: 'public' | 'private';
}

export async function uploadFile(
    file: File,
    options: {
        isPrivate?: boolean;
        accept?: string[];
        maxSize?: number;
        onProgress?: (percent: number) => void;
    } = {}
): Promise<UploadResponse> {
    const maxSize = options.maxSize ?? MAX_UPLOAD_FILE_SIZE;
    const accept = options.accept ?? ACCEPTED_UPLOAD_MIMES;

    // Guard 1: Client-side maxSize check
    if (file.size > maxSize) {
        throw new Error(`Ukuran file (${(file.size / (1024 * 1024)).toFixed(1)} MB) melebihi batas maksimal ${Math.round(maxSize / (1024 * 1024))} MB.`);
    }

    // Guard 2: Client-side accept check
    if (!accept.includes(file.type.toLowerCase())) {
        throw new Error(`Format file ${file.type} tidak didukung. Harap gunakan format: ${accept.join(', ')}.`);
    }

    if (options.onProgress) {
        options.onProgress(20);
    }

    const formData = new FormData();
    formData.append('file', file);
    if (options.isPrivate) {
        formData.append('is_private', '1');
    }

    const token = getToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
    };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    if (options.onProgress) {
        options.onProgress(50);
    }

    const response = await fetch(`${API_URL}/v1/uploads`, {
        method: 'POST',
        headers,
        body: formData,
    });

    if (options.onProgress) {
        options.onProgress(90);
    }

    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Gagal mengunggah file.');
    }

    if (options.onProgress) {
        options.onProgress(100);
    }

    const data = result.data || {};
    const uploadData: UploadResponse = {
        url: String(data.url || ''),
        temporary_url: typeof data.temporary_url === 'string' ? data.temporary_url : undefined,
        temporaryUrl: typeof data.temporaryUrl === 'string' ? data.temporaryUrl : undefined,
        signed_url: typeof data.signed_url === 'string' ? data.signed_url : undefined,
        path: String(data.path || ''),
        size: Number(data.size || 0),
        mime_type: String(data.mime_type || ''),
        visibility: data.visibility === 'private' ? 'private' : 'public',
    };

    return uploadData;
}
