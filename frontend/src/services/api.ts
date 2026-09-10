import { Booking, Room, User } from '@/types';

const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api').replace(/\/$/, '');
const TOKEN_KEY = 'meetspace_auth_token';

function getToken(): string | null {
    return typeof window === 'undefined' ? null : window.localStorage.getItem(TOKEN_KEY);
}

function authHeaders(): HeadersInit {
    const token = getToken();
    return token ? { Authorization: `Bearer ${token}` } : {};
}

async function getErrorMessage(response: Response, fallback: string): Promise<string> {
    const data = await response.json().catch(() => ({}));
    return data.message || fallback;
}

export async function login(email: string, password: string): Promise<{ token: string; user: User }> {
    const response = await fetch(`${API_URL}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });

    if (!response.ok) throw new Error(await getErrorMessage(response, 'Email atau password salah'));
    return response.json();
}

export async function getCurrentUser(): Promise<User> {
    const response = await fetch(`${API_URL}/me`, { headers: authHeaders() });
    if (!response.ok) throw new Error('Sesi login sudah berakhir');
    return response.json();
}

export async function logout(): Promise<void> {
    const response = await fetch(`${API_URL}/logout`, {
        method: 'POST',
        headers: authHeaders()
    });
    if (!response.ok && response.status !== 401) {
        throw new Error(await getErrorMessage(response, 'Gagal logout'));
    }
}

export function saveToken(token: string): void {
    window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
    window.localStorage.removeItem(TOKEN_KEY);
}

export async function getRooms(): Promise<Room[]> {
    const res = await fetch(`${API_URL}/v1/rooms`, { headers: authHeaders() });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal mengambil data ruangan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function getBookings(): Promise<Booking[]> {
    const res = await fetch(`${API_URL}/v1/bookings`, { headers: authHeaders() });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal mengambil data pemesanan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function createBooking(payload: {
    room_id: number;
    title: string;
    start_time: string;
    end_time: string;
    notes?: string;
}): Promise<Booking> {
    const res = await fetch(`${API_URL}/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) {
        throw new Error(await getErrorMessage(res, 'Gagal membuat pemesanan'));
    }
    return res.json();
}

export async function cancelBooking(id: number): Promise<void> {
    const res = await fetch(`${API_URL}/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal membatalkan pemesanan'));
}

export async function deleteBooking(id: number): Promise<void> {
    const res = await fetch(`${API_URL}/v1/bookings/${id}`, {
        method: 'DELETE',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menghapus pemesanan'));
}

export async function getAvailability(): Promise<Booking[]> {
    const res = await fetch(`${API_URL}/v1/bookings/availability`, { headers: authHeaders() });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal mengambil ketersediaan ruangan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function updateBooking(id: number, payload: {
    room_id: number;
    title: string;
    start_time: string;
    end_time: string;
    notes?: string;
}): Promise<Booking> {
    const res = await fetch(`${API_URL}/v1/bookings/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui pemesanan'));
    return res.json();
}