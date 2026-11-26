/**
 * Frontend API Cache & Invalidation Layer (Audit 496 - Issue #164).
 * Menangani in-memory caching, invalidasi instan saat mutasi,
 * dan pencegahan race conditions akibat request beruntun.
 */

interface CacheEntry<T> {
    data: T;
    expiresAt: number;
    tag?: string;
}

const memoryCache = new Map<string, CacheEntry<unknown>>();

// Map untuk melacak in-flight request guna mencegah race condition
const inFlightControllers = new Map<string, AbortController>();

/**
 * Simpan data ke cache dengan TTL (default 60 detik) dan tag kategori.
 */
export function setApiCache<T>(key: string, data: T, ttlMs = 60000, tag?: string): void {
    memoryCache.set(key, {
        data,
        expiresAt: Date.now() + ttlMs,
        tag,
    });
}

/**
 * Ambil data dari cache jika belum kedaluwarsa.
 */
export function getApiCache<T>(key: string): T | null {
    const entry = memoryCache.get(key) as CacheEntry<T> | undefined;
    if (!entry) return null;

    if (Date.now() > entry.expiresAt) {
        memoryCache.delete(key);
        return null;
    }

    return entry.data;
}

/**
 * Invalidasi cache berdasarkan tag spesifik (misal: 'rooms', 'bookings', 'users')
 * atau seluruh cache jika tag tidak diberikan.
 */
export function invalidateApiCache(tag?: string): void {
    if (!tag) {
        memoryCache.clear();
        return;
    }

    for (const [key, entry] of memoryCache.entries()) {
        if (entry.tag === tag || key.includes(tag)) {
            memoryCache.delete(key);
        }
    }
}

/**
 * Bersihkan seluruh isi cache (biasanya dipanggil saat logout atau mutasi global).
 */
export function clearApiCache(): void {
    memoryCache.clear();
}

/**
 * Dapatkan AbortSignal untuk request dengan key tertentu.
 * Jika ada request sebelumnya dengan key yang sama yang masih berjalan,
 * request lama akan di-abort untuk mencegah race condition.
 */
export function getOrCreateAbortSignal(key: string): AbortSignal {
    const existingController = inFlightControllers.get(key);
    if (existingController) {
        existingController.abort(); // Batalkan request lama yang belum selesai
    }

    const newController = new AbortController();
    inFlightControllers.set(key, newController);
    return newController.signal;
}

/**
 * Selesaikan pelacakan in-flight request.
 */
export function finalizeInFlightRequest(key: string): void {
    inFlightControllers.delete(key);
}
