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

export async function createRoom(payload: {
    name: string;
    capacity: number;
    location: string;
    description?: string;
    facilities?: string[];
    is_active: boolean;
}): Promise<Room> {
    const res = await fetch(`${API_URL}/v1/rooms`, {
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
    const res = await fetch(`${API_URL}/v1/rooms/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui ruangan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function deleteRoom(id: number): Promise<void> {
    const res = await fetch(`${API_URL}/v1/rooms/${id}`, {
        method: 'DELETE',
        headers: authHeaders()
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal menonaktifkan ruangan'));
}

export async function getBookings(): Promise<Booking[]> {
    const res = await fetch(`${API_URL}/v1/bookings`, { headers: authHeaders() });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal mengambil data pemesanan'));
    const data = await res.json();
    return data.data ?? data;
}

export async function getUsers(): Promise<User[]> {
    const res = await fetch(`${API_URL}/v1/users`, { headers: authHeaders() });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal mengambil data pengguna'));
    const data = await res.json();
    return data.data ?? data;
}

export async function updateUser(id: number, payload: { role: User['role']; status: User['status'] }): Promise<User> {
    const res = await fetch(`${API_URL}/v1/users/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error(await getErrorMessage(res, 'Gagal memperbarui pengguna'));
    const data = await res.json();
    return data.data ?? data;
}

async function updateBookingStatus(id: number, action: 'approve' | 'reject'): Promise<Booking> {
    const res = await fetch(`${API_URL}/v1/bookings/${id}/${action}`, {
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
    const res = await fetch(`${API_URL}/v1/bookings/${id}/reject`, {
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

export async function cancelBooking(id: number, cancellation_reason: string): Promise<void> {
    const res = await fetch(`${API_URL}/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify({ cancellation_reason })
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
    participant_count: number;
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

