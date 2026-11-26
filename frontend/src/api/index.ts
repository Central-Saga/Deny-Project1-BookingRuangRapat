/**
 * Frontend Centralized API Module (Audit 496 - Issue #164).
 * Seluruh endpoint dan interaksi data backend dipusatkan di sini.
 */

import { Booking, Room, User } from '@/types';
import {
    apiClient,
    API_URL,
    authHeaders,
    clearToken,
    getAllPages,
    getErrorMessage,
    getToken,
    hasToken,
    saveToken,
    type ApiErrorEnvelope,
    type ApiErrorItem,
    type RequestOptions,
} from './client';
import {
    clearApiCache,
    getApiCache,
    invalidateApiCache,
    setApiCache,
} from './cache';

// Re-export core primitives & cache tools
export {
    apiClient,
    API_URL,
    authHeaders,
    clearApiCache,
    clearToken,
    getApiCache,
    getErrorMessage,
    getToken,
    hasToken,
    invalidateApiCache,
    saveToken,
    setApiCache,
    type ApiErrorEnvelope,
    type ApiErrorItem,
    type RequestOptions,
};

// ==========================================
// Authentication
// ==========================================

export async function login(email: string, password: string): Promise<{ token: string; user: User }> {
    return apiClient<{ token: string; user: User }>('/login', {
        method: 'POST',
        skipAuth: true,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    }).catch((err) => {
        throw new Error(err.message || 'Email atau password salah');
    });
}

export async function getCurrentUser(): Promise<User> {
    return apiClient<User>('/me', {
        method: 'GET',
        useCache: true,
        cacheTtlMs: 30000,
    }).catch((err) => {
        throw new Error(err.message || 'Gagal memeriksa sesi login.');
    });
}

export async function logout(): Promise<void> {
    try {
        await apiClient('/logout', {
            method: 'POST',
        });
    } catch (err: unknown) {
        if (err instanceof Error && !err.message.includes('Sesi login berakhir')) {
            throw new Error(err.message || 'Gagal logout');
        }
    } finally {
        clearToken();
    }
}

// ==========================================
// Rooms API
// ==========================================

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
    const data = await apiClient<{ data?: Room } | Room>('/v1/rooms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'rooms',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal menambahkan ruangan');
    });
    return (data as { data?: Room }).data ?? (data as Room);
}

export async function updateRoom(
    id: number,
    payload: {
        name: string;
        capacity: number;
        location: string;
        facilities?: string[];
        description?: string;
        is_active: boolean;
    }
): Promise<Room> {
    const data = await apiClient<{ data?: Room } | Room>(`/v1/rooms/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'rooms',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal memperbarui ruangan');
    });
    return (data as { data?: Room }).data ?? (data as Room);
}

export async function deleteRoom(id: number): Promise<void> {
    await apiClient(`/v1/rooms/${id}`, {
        method: 'DELETE',
        cacheTag: 'rooms',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal menonaktifkan ruangan');
    });
}

// ==========================================
// Bookings API
// ==========================================

export async function getBookings(): Promise<Booking[]> {
    return getAllPages<Booking>('/v1/bookings', 'Gagal mengambil data pemesanan');
}

export async function getAvailability(): Promise<Booking[]> {
    return getAllPages<Booking>('/v1/bookings/availability', 'Gagal mengambil ketersediaan ruangan');
}

export async function createBooking(payload: {
    room_id: number;
    title: string;
    participant_count: number;
    start_time: string;
    end_time: string;
    notes?: string;
}): Promise<Booking> {
    const data = await apiClient<{ data?: Booking } | Booking>('/v1/bookings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal membuat pemesanan');
    });
    invalidateApiCache('availability');
    return (data as { data?: Booking }).data ?? (data as Booking);
}

export async function updateBooking(
    id: number,
    payload: {
        room_id: number;
        title: string;
        participant_count: number;
        start_time: string;
        end_time: string;
        notes?: string;
    }
): Promise<Booking> {
    const data = await apiClient<{ data?: Booking } | Booking>(`/v1/bookings/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal memperbarui pemesanan');
    });
    invalidateApiCache('availability');
    return (data as { data?: Booking }).data ?? (data as Booking);
}

export async function approveBooking(id: number): Promise<Booking> {
    const data = await apiClient<{ data?: Booking } | Booking>(`/v1/bookings/${id}/approve`, {
        method: 'PATCH',
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal menyetujui booking');
    });
    invalidateApiCache('availability');
    return (data as { data?: Booking }).data ?? (data as Booking);
}

export async function rejectBooking(id: number, approval_notes: string): Promise<Booking> {
    const data = await apiClient<{ data?: Booking } | Booking>(`/v1/bookings/${id}/reject`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ approval_notes }),
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal menolak booking');
    });
    invalidateApiCache('availability');
    return (data as { data?: Booking }).data ?? (data as Booking);
}

export async function cancelBooking(id: number, cancellation_reason: string): Promise<void> {
    await apiClient(`/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cancellation_reason }),
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal membatalkan pemesanan');
    });
    invalidateApiCache('availability');
}

export async function deleteBooking(id: number): Promise<void> {
    await apiClient(`/v1/bookings/${id}`, {
        method: 'DELETE',
        cacheTag: 'bookings',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal menghapus pemesanan');
    });
    invalidateApiCache('availability');
}

// ==========================================
// Users API
// ==========================================

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
    const data = await apiClient<{ data?: User } | User>('/v1/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'users',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal membuat akun pengguna');
    });
    return (data as { data?: User }).data ?? (data as User);
}

export async function updateUser(
    id: number,
    payload: { role: User['role']; status: User['status'] }
): Promise<User> {
    const data = await apiClient<{ data?: User } | User>(`/v1/users/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'users',
    }).catch((err) => {
        throw new Error(err.message || 'Gagal memperbarui pengguna');
    });
    return (data as { data?: User }).data ?? (data as User);
}
