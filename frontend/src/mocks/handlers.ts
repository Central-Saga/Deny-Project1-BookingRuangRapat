import { http, HttpResponse } from 'msw';

// Mock database data
export const mockRooms = [
    {
        id: 1,
        name: 'Ruang Rinjani',
        capacity: 12,
        location: 'Lantai 2 Gedung A',
        facilities: ['Proyektor', 'Whiteboard', 'AC', 'Video Conference'],
        description: 'Ruang rapat kapasitas sedang dengan peralatan video conference lengkap.',
        is_active: true,
        created_at: '2026-01-01T08:00:00Z',
    },
    {
        id: 2,
        name: 'Ruang Semeru',
        capacity: 25,
        location: 'Lantai 3 Gedung Utama',
        facilities: ['Proyektor 4K', 'Smart Board', 'Sound System'],
        description: 'Ruang rapat besar untuk koordinasi antar divisi.',
        is_active: true,
        created_at: '2026-01-01T08:00:00Z',
    },
];

export const mockBookings = [
    {
        id: 101,
        user_id: 2,
        room_id: 1,
        title: 'Sprint Planning Q1',
        participant_count: 8,
        start_time: '2026-12-01 09:00:00',
        end_time: '2026-12-01 11:00:00',
        status: 'approved',
        notes: 'Agenda sprint planning',
        room: mockRooms[0],
        user: { id: 2, name: 'Budi Santoso', email: 'budi@example.com', role: 'karyawan' },
    },
];

export const mockUsers = [
    { id: 1, name: 'Admin Facility', email: 'admin@example.com', role: 'admin', status: 'active' },
    { id: 2, name: 'Budi Santoso', email: 'budi@example.com', role: 'karyawan', status: 'active' },
];

/**
 * Mock Service Worker (MSW) Handlers untuk Seluruh Endpoint API Frontend.
 * Mensimulasikan backend Laravel termasuk skema respon sukses, uniform error 422/401/403/404/500, dan headers.
 */
export const handlers = [
    // ------------------------------------------
    // Auth Handlers
    // ------------------------------------------
    http.post('*/api/login', async ({ request }) => {
        const body = (await request.json().catch(() => ({}))) as { email?: string; password?: string };

        if (!body.email || !body.password) {
            return HttpResponse.json(
                {
                    status: false,
                    message: 'Validasi gagal.',
                    errors: [
                        ...(!body.email ? [{ field: 'email', message: 'Email wajib diisi.' }] : []),
                        ...(!body.password ? [{ field: 'password', message: 'Password wajib diisi.' }] : []),
                    ],
                },
                { status: 422 }
            );
        }

        if (body.email === 'admin@example.com' && body.password === 'Password123!') {
            return HttpResponse.json({
                status: true,
                message: 'Login berhasil.',
                token: 'mock-jwt-admin-token-12345',
                user: mockUsers[0],
                data: {
                    token: 'mock-jwt-admin-token-12345',
                    user: mockUsers[0],
                },
            });
        }

        if (body.email === 'budi@example.com' && body.password === 'Password123!') {
            return HttpResponse.json({
                status: true,
                message: 'Login berhasil.',
                token: 'mock-jwt-employee-token-67890',
                user: mockUsers[1],
                data: {
                    token: 'mock-jwt-employee-token-67890',
                    user: mockUsers[1],
                },
            });
        }

        return HttpResponse.json(
            {
                status: false,
                message: 'Email atau password salah.',
                errors: [],
            },
            { status: 401 }
        );
    }),

    http.get('*/api/me', ({ request }) => {
        const auth = request.headers.get('Authorization');
        if (!auth || !auth.startsWith('Bearer ')) {
            return HttpResponse.json(
                { status: false, message: 'Autentikasi diperlukan.', errors: [] },
                { status: 401 }
            );
        }

        if (auth.includes('admin')) {
            return HttpResponse.json(mockUsers[0]);
        }

        return HttpResponse.json(mockUsers[1]);
    }),

    http.post('*/api/logout', () => {
        return HttpResponse.json({
            status: true,
            message: 'Logout berhasil.',
        });
    }),

    http.post('*/api/change-password', async ({ request }) => {
        const body = (await request.json().catch(() => ({}))) as { current_password?: string; new_password?: string };
        if (!body.new_password || body.new_password.length < 8) {
            return HttpResponse.json(
                {
                    status: false,
                    message: 'Validasi gagal.',
                    errors: [{ field: 'new_password', message: 'Password minimal 8 karakter.' }],
                },
                { status: 422 }
            );
        }

        return HttpResponse.json({
            status: true,
            message: 'Password berhasil diperbarui.',
        });
    }),

    // ------------------------------------------
    // Rooms Handlers
    // ------------------------------------------
    http.get('*/api/v1/rooms', () => {
        return HttpResponse.json({
            data: mockRooms,
            meta: { current_page: 1, last_page: 1, total: mockRooms.length },
        });
    }),

    http.get('*/api/v1/rooms/:id', ({ params }) => {
        const id = Number(params.id);
        const room = mockRooms.find((r) => r.id === id);
        if (!room) {
            return HttpResponse.json(
                { status: false, message: 'Ruangan tidak ditemukan.', errors: [] },
                { status: 404 }
            );
        }
        return HttpResponse.json({ data: room });
    }),

    http.post('*/api/v1/rooms', async ({ request }) => {
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;

        if (!body.name || !body.capacity || (body.capacity as number) <= 0) {
            return HttpResponse.json(
                {
                    status: false,
                    message: 'Validasi gagal.',
                    errors: [
                        ...(!body.name ? [{ field: 'name', message: 'Nama ruangan wajib diisi.' }] : []),
                        ...(!body.capacity || (body.capacity as number) <= 0
                            ? [{ field: 'capacity', message: 'Kapasitas ruangan harus minimal 1 orang.' }]
                            : []),
                    ],
                },
                { status: 422 }
            );
        }

        const newRoom = {
            id: mockRooms.length + 1,
            name: body.name as string,
            capacity: body.capacity as number,
            location: (body.location as string) || 'Lantai 1',
            facilities: (body.facilities as string[]) || [],
            is_active: body.is_active !== false,
            created_at: new Date().toISOString(),
        };

        return HttpResponse.json({ data: newRoom }, { status: 201 });
    }),

    http.put('*/api/v1/rooms/:id', async ({ params, request }) => {
        const id = Number(params.id);
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
        const room = mockRooms.find((r) => r.id === id);

        if (!room) {
            return HttpResponse.json(
                { status: false, message: 'Ruangan tidak ditemukan.', errors: [] },
                { status: 404 }
            );
        }

        const updated = { ...room, ...body, id };
        return HttpResponse.json({ data: updated });
    }),

    http.delete('*/api/v1/rooms/:id', ({ params }) => {
        const id = Number(params.id);
        const room = mockRooms.find((r) => r.id === id);
        if (!room) {
            return HttpResponse.json(
                { status: false, message: 'Ruangan tidak ditemukan.', errors: [] },
                { status: 404 }
            );
        }
        return HttpResponse.json({ status: true, message: 'Ruangan dinonaktifkan.' });
    }),

    // ------------------------------------------
    // Bookings Handlers
    // ------------------------------------------
    http.get('*/api/v1/bookings', () => {
        return HttpResponse.json({
            data: mockBookings,
            meta: { current_page: 1, last_page: 1, total: mockBookings.length },
        });
    }),

    http.get('*/api/v1/bookings/availability', () => {
        return HttpResponse.json({
            data: mockBookings.map((b) => ({
                id: b.id,
                room_id: b.room_id,
                start_time: b.start_time,
                end_time: b.end_time,
                status: b.status,
            })),
            meta: { current_page: 1, last_page: 1, total: mockBookings.length },
        });
    }),

    http.get('*/api/v1/bookings/:id', ({ params }) => {
        const id = Number(params.id);
        const booking = mockBookings.find((b) => b.id === id);
        if (!booking) {
            return HttpResponse.json(
                { status: false, message: 'Booking tidak ditemukan.', errors: [] },
                { status: 404 }
            );
        }
        return HttpResponse.json({ data: booking });
    }),

    http.post('*/api/v1/bookings', async ({ request }) => {
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;

        // Cek konflik jadwal (simulasi)
        if (body.start_time === '2026-12-01 09:00:00' && body.room_id === 1) {
            return HttpResponse.json(
                {
                    status: false,
                    message: 'Ruangan sudah dibooking pada jadwal tersebut.',
                    errors: [{ field: 'start_time', message: 'Waktu bentrok dengan booking eksisting.' }],
                },
                { status: 409 }
            );
        }

        // Cek kapasitas
        if ((body.participant_count as number) > 100) {
            return HttpResponse.json(
                {
                    status: false,
                    message: 'Validasi gagal.',
                    errors: [{ field: 'participant_count', message: 'Jumlah peserta melebihi kapasitas ruangan.' }],
                },
                { status: 422 }
            );
        }

        const newBooking = {
            id: mockBookings.length + 101,
            user_id: 2,
            room_id: body.room_id as number,
            title: body.title as string,
            participant_count: body.participant_count as number,
            start_time: body.start_time as string,
            end_time: body.end_time as string,
            status: 'pending',
            notes: (body.notes as string) || '',
        };

        return HttpResponse.json({ data: newBooking }, { status: 201 });
    }),

    http.put('*/api/v1/bookings/:id', async ({ params, request }) => {
        const id = Number(params.id);
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
        return HttpResponse.json({ data: { id, ...body, status: 'pending' } });
    }),

    http.patch('*/api/v1/bookings/:id/approve', ({ params }) => {
        const id = Number(params.id);
        return HttpResponse.json({ data: { id, status: 'approved' } });
    }),

    http.patch('*/api/v1/bookings/:id/reject', async ({ params, request }) => {
        const id = Number(params.id);
        const body = (await request.json().catch(() => ({}))) as { approval_notes?: string };
        return HttpResponse.json({ data: { id, status: 'rejected', notes: body.approval_notes } });
    }),

    http.patch('*/api/v1/bookings/:id/cancel', async ({ params, request }) => {
        const id = Number(params.id);
        const body = (await request.json().catch(() => ({}))) as { cancellation_reason?: string };
        return HttpResponse.json({ status: true, message: 'Booking berhasil dibatalkan.', id, ...body });
    }),

    http.delete('*/api/v1/bookings/:id', ({ params }) => {
        const id = Number(params.id);
        return HttpResponse.json({ status: true, message: 'Booking dihapus.', id });
    }),

    // ------------------------------------------
    // Users Handlers
    // ------------------------------------------
    http.get('*/api/v1/users', () => {
        return HttpResponse.json({
            data: mockUsers,
            meta: { current_page: 1, last_page: 1, total: mockUsers.length },
        });
    }),

    http.post('*/api/v1/users', async ({ request }) => {
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
        const newUser = {
            id: mockUsers.length + 1,
            name: body.name as string,
            email: body.email as string,
            role: body.role as string,
            status: body.status as string,
        };
        return HttpResponse.json({ data: newUser }, { status: 201 });
    }),

    http.put('*/api/v1/users/:id', async ({ params, request }) => {
        const id = Number(params.id);
        const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
        return HttpResponse.json({ data: { id, ...body } });
    }),

    // ------------------------------------------
    // Upload & Health Handlers
    // ------------------------------------------
    http.post('*/api/v1/uploads', () => {
        return HttpResponse.json(
            {
                success: true,
                status: true,
                data: {
                    path: 'uploads/2026/room-banner.jpg',
                    url: 'http://localhost:8000/storage/uploads/2026/room-banner.jpg',
                },
            },
            { status: 201 }
        );
    }),

    http.get('*/up', () => {
        return HttpResponse.json({ status: 'ok', timestamp: new Date().toISOString() });
    }),
];
