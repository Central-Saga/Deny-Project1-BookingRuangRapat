import { Booking, Room, User } from '@/types';

const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api').replace(/\/$/, '');
const TOKEN_KEY = 'meetspace_auth_token';

function getToken(): string | null {
    return typeof window === 'undefined' ? null : window.localStorage.getItem(TOKEN_KEY);
}

function authHeaders(): HeadersInit {
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

// Satu pintu untuk semua request API yang membutuhkan autentikasi.
async function authenticatedFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
    let response: Response;
    try {
        response = await fetch(input, init);
    } catch {
        throw new Error('Tidak dapat terhubung ke server API. Periksa koneksi dan coba lagi.');
    }

    if (response.status === 401) {
        clearToken();
        if (typeof window !== 'undefined') {
            window.location.replace('/');
        }
        throw new Error('Sesi login berakhir. Silakan masuk kembali.');
    }

    return response;
}

export async function login(email: string, password: string): Promise<{ token: string; user: User }> {
    const response = await fetch(`${API_URL}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password })
    }).catch(() => {
        throw new Error('Tidak dapat terhubung ke server API. Periksa koneksi dan coba lagi.');
    });

    if (!response.ok) throw new Error(await getErrorMessage(response, 'Email atau password salah'));
    return response.json();
}

export async function getCurrentUser(): Promise<User> {
    const response = await authenticatedFetch(`${API_URL}/me`, { headers: authHeaders() });
    if (!response.ok) throw new Error(await getErrorMessage(response, 'Gagal memeriksa sesi login.'));
    return response.json();
}

export async function logout(): Promise<void> {
    const response = await authenticatedFetch(`${API_URL}/logout`, {
        method: 'POST',
        headers: authHeaders()
    });
    if (!response.ok && response.status !== 401) {
        throw new Error(await getErrorMessage(response, 'Gagal logout'));
    }
}

export function saveToken(token: string): void {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TOKEN_KEY, token);
    }
}

export function clearToken(): void {
    if (typeof window !== 'undefined') {
        window.localStorage.removeItem(TOKEN_KEY);
    }
}

// Dashboard lama memakai array. Ambil semua halaman agar tidak ada data tersembunyi.
// UI pagination dapat ditambahkan nantinya tanpa mengubah kontrak API.
type PageResponse<T> = {
    data: T[];
    meta?: { current_page?: number; last_page?: number; per_page?: number; total?: number };
};

async function getAllPages<T>(path: string, fallback: string): Promise<T[]> {
    const items: T[] = [];
    let page = 1;
    let lastPage = 1;

    do {
        const separator = path.includes('?') ? '&' : '?';
        const response = await authenticatedFetch(`${API_URL}${path}${separator}page=${page}&per_page=100`, {
            headers: authHeaders(),
        });
        if (!response.ok) throw new Error(await getErrorMessage(response, fallback));

        const payload = (await response.json()) as PageResponse<T>;
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
export async function getRooms(): Promise<Room[]> {
    return getAllPages<Room>('/v1/rooms', 'Gagal mengambil data ruangan');
}

export async function createRoom(payload: {
    name: string;
    capacity: number;
    location: string;
    description?: string;
    facilities?: string[];
    is_active: boolean;
}): Promise<Room> {
    const res = await authenticatedFetch(`${API_URL}/v1/rooms`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menambahkan ruangan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function updateRoom(id: number, payload: {
    name: string;
    capacity: number;
    location: string;
    facilities?: string[];
    description?: string;
    is_active: boolean;
}): Promise<Room> {
    const res = await authenticatedFetch(`${API_URL}/v1/rooms/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui ruangan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function deleteRoom(id: number): Promise<void> {
    const res = await authenticatedFetch(`${API_URL}/v1/rooms/${id}`, {
        method: 'DELETE',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menonaktifkan ruangan'));
}

export async function getBookings(): Promise<Booking[]> {
    return getAllPages<Booking>('/v1/bookings', 'Gagal mengambil data pemesanan');
}

export async function getUsers(): Promise<User[]> {
    return getAllPages<User>('/v1/users', 'Gagal mengambil data pengguna');
}

export async function createUser(payload: {
    name: string;
    email: string;
    password: string;
    role: User['role'];
    status: User['status'];
}): Promise<User> {
    const res = await authenticatedFetch(`${API_URL}/v1/users`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal membuat akun pengguna'));
    const data = await res.json();
    return data.data ?? data;
}

export async function updateUser(id: number, payload: { role: User['role']; status: User['status'] }): Promise<User> {
    const res = await authenticatedFetch(`${API_URL}/v1/users/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui pengguna'));
    const data = await res.json();
    return data.data ?? data;
}

async function updateBookingStatus(id: number, action: 'approve' | 'reject'): Promise<Booking> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings/${id}/${action}`, {
        method: 'PATCH',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, `Gagal ${action === 'approve' ? 'menyetujui' : 'menolak'} booking`));
    const data = await res.json();
    return data.data ?? data;
}

export function approveBooking(id: number): Promise<Booking> {
    return updateBookingStatus(id, 'approve');
}

export async function rejectBooking(id: number, approval_notes: string): Promise<Booking> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings/${id}/reject`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify({ approval_notes })
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menolak booking'));
    const data = await res.json();
    return data.data ?? data;
}

export async function createBooking(payload: {
    room_id: number;
    title: string;
    participant_count: number;
    start_time: string;
    end_time: string;
    notes?: string;
}): Promise<Booking> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) {
        throw new Error(await getErrorMessage(res, 'Gagal membuat pemesanan'));
    }
    const data = await res.json();
    return data.data ?? data;
}

export async function cancelBooking(id: number, cancellation_reason: string): Promise<void> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify({ cancellation_reason })
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal membatalkan pemesanan'));
}

export async function deleteBooking(id: number): Promise<void> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings/${id}`, {
        method: 'DELETE',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menghapus pemesanan'));
}

export async function getAvailability(): Promise<Booking[]> {
    return getAllPages<Booking>('/v1/bookings/availability', 'Gagal mengambil ketersediaan ruangan');
}

export async function updateBooking(id: number, payload: {
    room_id: number;
    title: string;
    participant_count: number;
    start_time: string;
    end_time: string;
    notes?: string;
}): Promise<Booking> {
    const res = await authenticatedFetch(`${API_URL}/v1/bookings/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui pemesanan'));
    const data = await res.json();
    return data.data ?? data;
}
