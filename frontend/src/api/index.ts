/**
 * Frontend Centralized API Module (Audit 496 - Issue #164).
 * Seluruh endpoint dan interaksi data backend dipusatkan di sini.
 */

import { Booking, Room, User } from '@/types';
import {
    apiClient,
    API_URL,
    ApiError,
    authHeaders,
    clearToken,
    extractFieldErrors,
    getAllPages,
    getErrorMessage,
    getToken,
    hasToken,
    saveToken,
    uploadFile,
    MAX_UPLOAD_FILE_SIZE,
    ACCEPTED_UPLOAD_MIMES,
    type ApiErrorEnvelope,
    type ApiErrorItem,
    type RequestOptions,
    type UploadResponse,
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
    ApiError,
    authHeaders,
    clearApiCache,
    clearToken,
    extractFieldErrors,
    getApiCache,
    getErrorMessage,
    getToken,
    hasToken,
    invalidateApiCache,
    saveToken,
    setApiCache,
    uploadFile,
    MAX_UPLOAD_FILE_SIZE,
    ACCEPTED_UPLOAD_MIMES,
    type ApiErrorEnvelope,
    type ApiErrorItem,
    type RequestOptions,
    type UploadResponse,
};

export interface DataEnvelope<T> {
    data: T;
}

function hasDataEnvelope<T>(payload: DataEnvelope<T> | T): payload is DataEnvelope<T> {
    return Boolean(payload && typeof payload === 'object' && 'data' in payload);
}

function unwrapResponse<T>(payload: DataEnvelope<T> | T): T {
    if (hasDataEnvelope(payload)) {
        return payload.data;
    }
    return payload;
}

function rethrowError(err: unknown, fallback: string): never {
    if (err instanceof ApiError) {
        throw err;
    }
    throw new Error(err instanceof Error ? err.message : fallback);
}

// ==========================================
// Authentication
// ==========================================

export async function login(email: string, password: string): Promise<{ token: string; user: User }> {
    return apiClient<{ token: string; user: User }>('/login', {
        method: 'POST',
        skipAuth: true,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    }).catch((err) => rethrowError(err, 'Email atau password salah'));
}

export async function getCurrentUser(): Promise<User> {
    return apiClient<User>('/me', {
        method: 'GET',
        useCache: true,
        cacheTtlMs: 30000,
    }).catch((err) => rethrowError(err, 'Gagal memeriksa sesi login.'));
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
    const data = await apiClient<DataEnvelope<Room> | Room>('/v1/rooms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'rooms',
    }).catch((err) => rethrowError(err, 'Gagal menambahkan ruangan'));
    return unwrapResponse(data);
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
    const data = await apiClient<DataEnvelope<Room> | Room>(`/v1/rooms/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'rooms',
    }).catch((err) => rethrowError(err, 'Gagal memperbarui ruangan'));
    return unwrapResponse(data);
}

export async function deleteRoom(id: number): Promise<void> {
    await apiClient(`/v1/rooms/${id}`, {
        method: 'DELETE',
        cacheTag: 'rooms',
    }).catch((err) => rethrowError(err, 'Gagal menonaktifkan ruangan'));
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
    const data = await apiClient<DataEnvelope<Booking> | Booking>('/v1/bookings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal membuat pemesanan'));
    invalidateApiCache('availability');
    return unwrapResponse(data);
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
    const data = await apiClient<DataEnvelope<Booking> | Booking>(`/v1/bookings/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal memperbarui pemesanan'));
    invalidateApiCache('availability');
    return unwrapResponse(data);
}

export async function approveBooking(id: number): Promise<Booking> {
    const data = await apiClient<DataEnvelope<Booking> | Booking>(`/v1/bookings/${id}/approve`, {
        method: 'PATCH',
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal menyetujui booking'));
    invalidateApiCache('availability');
    return unwrapResponse(data);
}

export async function rejectBooking(id: number, approval_notes: string): Promise<Booking> {
    const data = await apiClient<DataEnvelope<Booking> | Booking>(`/v1/bookings/${id}/reject`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ approval_notes }),
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal menolak booking'));
    invalidateApiCache('availability');
    return unwrapResponse(data);
}

export async function cancelBooking(id: number, cancellation_reason: string): Promise<void> {
    await apiClient(`/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cancellation_reason }),
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal membatalkan pemesanan'));
    invalidateApiCache('availability');
}

export async function deleteBooking(id: number): Promise<void> {
    await apiClient(`/v1/bookings/${id}`, {
        method: 'DELETE',
        cacheTag: 'bookings',
    }).catch((err) => rethrowError(err, 'Gagal menghapus pemesanan'));
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
    const data = await apiClient<DataEnvelope<User> | User>('/v1/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'users',
    }).catch((err) => rethrowError(err, 'Gagal membuat akun pengguna'));
    return unwrapResponse(data);
}

export async function updateUser(
    id: number,
    payload: { role: User['role']; status: User['status'] }
): Promise<User> {
    const data = await apiClient<DataEnvelope<User> | User>(`/v1/users/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        cacheTag: 'users',
    }).catch((err) => rethrowError(err, 'Gagal memperbarui pengguna'));
    return unwrapResponse(data);
}
