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
    return null;
}

export function hasToken(): boolean {
    return Boolean(getToken());
}

export function saveToken(token: string): void {
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

export function authHeaders(): HeadersInit {
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
    const data = (await response.json().catch(() => ({}))) as ApiErrorEnvelope;
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
    const requestHeaders: Record<string, string> = {
        Accept: 'application/json',
        ...(skipAuth ? {} : (authHeaders() as Record<string, string>)),
        ...(headers as Record<string, string>),
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
    if (response.status === 401) {
        clearToken();
        if (typeof window !== 'undefined') {
            window.location.replace('/');
        }
        throw new Error('Sesi login berakhir. Silakan masuk kembali.');
    }

    if (!response.ok) {
        throw new Error(await getErrorMessage(response, `Permintaan gagal dengan status ${response.status}`));
    }

    if (response.status === 204) {
        // No Content
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            invalidateApiCache(tag);
        }
        return {} as T;
    }

    const data = (await response.json()) as T;

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
