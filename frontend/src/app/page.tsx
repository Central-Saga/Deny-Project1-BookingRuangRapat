'use client';

import { useState, useEffect, useMemo } from 'react';
import { z } from 'zod';
import { cancelBooking, clearToken, createBooking, getBookings, getCurrentUser, getRooms, login, logout, saveToken } from '@/services/api';
import { Booking, Room, User } from '@/types';

const bookingSchema = z.object({
    room_id: z.string().min(1, 'Ruangan wajib dipilih'),
    title: z.string().min(3, 'Judul rapat minimal 3 karakter'),
    date: z.string().min(1, 'Tanggal wajib diisi'),
    start_time: z.string().regex(/^([01]\d|2[0-3]):([0-5]\d)$/, 'Format jam mulai harus HH:mm (24-jam)'),
    end_time: z.string().regex(/^([01]\d|2[0-3]):([0-5]\d)$/, 'Format jam selesai harus HH:mm (24-jam)'),
    notes: z.string().max(500, 'Catatan maksimal 500 karakter').optional()
});

function formatBookingDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatBookingTime(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function getErrorMessage(error: unknown, fallback: string): string {
    return error instanceof Error ? error.message : fallback;
}

export default function Dashboard() {
    const [user, setUser] = useState<User | null>(null);
    const [authLoading, setAuthLoading] = useState<boolean>(true);
    const [loginData, setLoginData] = useState({ email: '', password: '' });
    const [loginError, setLoginError] = useState<string | null>(null);
    const [loggingIn, setLoggingIn] = useState<boolean>(false);
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Booking[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState<boolean>(false);
    const [search, setSearch] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    
    const [formData, setFormData] = useState({
        room_id: '',
        title: '',
        date: '',
        start_time: '',
        end_time: '',
        notes: ''
    });

    const activeBookings = useMemo(() => bookings.filter((booking) => booking.status !== 'rejected'), [bookings]);
    const filteredBookings = useMemo(() => bookings.filter((booking) => {
        const matchesStatus = statusFilter === 'all' || booking.status === statusFilter;
        const searchable = `${booking.title} ${booking.user?.name || ''}`.toLowerCase();
        return matchesStatus && searchable.includes(search.toLowerCase());
    }), [bookings, search, statusFilter]);

    useEffect(() => {
        async function initializeAuth() {
            const token = window.localStorage.getItem('meetspace_auth_token');
            if (!token) {
                setAuthLoading(false);
                return;
            }

            try {
                const currentUser = await getCurrentUser();
                setUser(currentUser);
                await loadData();
            } catch {
                clearToken();
                setUser(null);
            } finally {
                setAuthLoading(false);
            }
        }

        void initializeAuth();
    }, []);

    async function handleLogin(e: React.FormEvent) {
        e.preventDefault();
        setLoginError(null);
        setLoggingIn(true);

        try {
            const result = await login(loginData.email, loginData.password);
            saveToken(result.token);
            setUser(result.user);
            await loadData();
        } catch (err: unknown) {
            setLoginError(getErrorMessage(err, 'Login gagal. Periksa email dan password Anda.'));
        } finally {
            setLoggingIn(false);
        }
    }

    async function handleLogout() {
        try {
            await logout();
        } finally {
            clearToken();
            setUser(null);
            setRooms([]);
            setBookings([]);
        }
    }

    async function loadData() {
        try {
            setLoading(true);
            const [roomsData, bookingsData] = await Promise.all([getRooms(), getBookings()]);
            setRooms(roomsData);
            setBookings(bookingsData);
            setError(null);
        } catch (err: unknown) {
            setError(getErrorMessage(err, 'Gagal memuat data dari server'));
        } finally {
            setLoading(false);
        }
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setFormErrors({});

        const result = bookingSchema.safeParse(formData);
        if (!result.success) {
            const errors: Record<string, string> = {};
            result.error.issues.forEach((issue) => {
                if (issue.path[0]) {
                    errors[issue.path[0] as string] = issue.message;
                }
            });
            setFormErrors(errors);
            return;
        }

        try {
            setSubmitting(true);
            await createBooking({
                room_id: Number(formData.room_id),
                title: formData.title.trim(),
                start_time: `${formData.date} ${formData.start_time}:00`,
                end_time: `${formData.date} ${formData.end_time}:00`,
                notes: formData.notes.trim() || undefined
            });
            setFormData({ room_id: '', title: '', date: '', start_time: '', end_time: '', notes: '' });
            loadData();
            alert('Booking berhasil dibuat!');
        } catch (err: unknown) {
            alert(getErrorMessage(err, 'Gagal membuat pemesanan'));
        } finally {
            setSubmitting(false);
        }
    }

    async function handleCancel(id: number) {
        if (!confirm('Apakah Anda yakin ingin membatalkan jadwal rapat ini?')) return;
        try {
            await cancelBooking(id);
            loadData();
        } catch (err: unknown) {
            alert(getErrorMessage(err, 'Gagal membatalkan pemesanan'));
        }
    }

    if (authLoading) {
        return (
            <div className="min-h-screen bg-slate-100 flex items-center justify-center">
                <span className="text-slate-600 font-medium">Memeriksa sesi login...</span>
            </div>
        );
    }

    if (!user) {
        return (
            <main className="min-h-screen bg-slate-100 flex items-center justify-center p-4">
                <div className="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
                    <div className="flex items-center gap-3 mb-8">
                        <div className="bg-blue-600 text-white font-bold h-10 w-10 rounded-lg flex items-center justify-center shadow-md">MS</div>
                        <div>
                            <h1 className="font-bold text-xl text-slate-900">MeetSpace Enterprise</h1>
                            <p className="text-xs text-slate-500">Portal pemesanan ruang rapat</p>
                        </div>
                    </div>
                    <h2 className="text-2xl font-bold text-slate-900">Masuk ke akun Anda</h2>
                    <p className="text-sm text-slate-500 mt-1 mb-6">Gunakan akun karyawan atau admin untuk melanjutkan.</p>
                    {loginError && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{loginError}</div>}
                    <form onSubmit={handleLogin} className="space-y-4">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input id="email" type="email" required autoComplete="email" value={loginData.email} onChange={(e) => setLoginData({ ...loginData, email: e.target.value })} className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="nama@perusahaan.com" />
                        </div>
                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <input id="password" type="password" required autoComplete="current-password" value={loginData.password} onChange={(e) => setLoginData({ ...loginData, password: e.target.value })} className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" placeholder="Masukkan password" />
                        </div>
                        <button type="submit" disabled={loggingIn} className="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                            {loggingIn ? 'Memproses...' : 'Login'}
                        </button>
                    </form>
                    <p className="mt-6 text-center text-xs text-slate-400">Akun demo: admin@example.com atau karyawan@example.com / password</p>
                </div>
            </main>
        );
    }

    if (loading) {
        return (
            <div className="min-h-screen bg-slate-100 flex items-center justify-center">
                <div className="flex items-center space-x-3 text-slate-600 font-medium">
                    <svg className="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat sistem MeetSpace...</span>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-slate-100 flex items-center justify-center p-4">
                <div className="bg-white border border-red-200 p-6 rounded-xl shadow-sm max-w-md w-full text-center">
                    <div className="text-red-500 font-semibold mb-2">Gagal Terhubung ke Server</div>
                    <p className="text-slate-600 text-sm mb-4">{error}</p>
                    <button onClick={loadData} className="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition">
                        Coba Lagi
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-100 text-slate-800 font-sans">
            <header className="bg-white border-b border-slate-200 sticky top-0 z-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                    <div className="flex items-center space-x-3">
                        <div className="bg-blue-600 text-white font-bold h-9 w-9 rounded-lg flex items-center justify-center shadow-md">
                            MS
                        </div>
                        <span className="hidden sm:inline font-bold text-lg text-slate-900 tracking-tight">MeetSpace Enterprise</span>
                    </div>
                    <div className="flex items-center gap-2 sm:gap-3">
                        <div className="text-right hidden sm:block">
                            <p className="text-sm font-semibold text-slate-900">{user.name}</p>
                            <p className="text-[11px] uppercase tracking-wide text-slate-500">{user.role}</p>
                        </div>
                        <button type="button" onClick={handleLogout} aria-label="Logout" className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700">
                            Keluar
                        </button>
                    </div>
                </div>
            </header>

            <main className="max-w-7xl mx-auto p-6 lg:p-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Pemesanan Ruang Rapat</h1>
                    <p className="text-sm text-slate-500 mt-1">Pilih ruangan dan jadwalkan pertemuan tim Anda dengan mudah dan terstruktur.</p>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm"><p className="text-xs font-semibold text-slate-500">Agenda aktif</p><p className="text-2xl font-bold text-slate-900 mt-2">{activeBookings.length}</p><p className="text-xs text-slate-400 mt-1">booking berjalan</p></div>
                    <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm"><p className="text-xs font-semibold text-slate-500">Menunggu approval</p><p className="text-2xl font-bold text-amber-600 mt-2">{bookings.filter((booking) => booking.status === 'pending').length}</p><p className="text-xs text-slate-400 mt-1">perlu ditindaklanjuti</p></div>
                    <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm"><p className="text-xs font-semibold text-slate-500">Ruangan aktif</p><p className="text-2xl font-bold text-emerald-600 mt-2">{rooms.filter((room) => room.is_active).length}</p><p className="text-xs text-slate-400 mt-1">dari {rooms.length} ruangan</p></div>
                    <div className="bg-red-600 p-5 rounded-2xl shadow-sm text-white"><p className="text-xs font-semibold text-red-100">Total agenda</p><p className="text-2xl font-bold mt-2">{bookings.length}</p><p className="text-xs text-red-100 mt-1">termasuk riwayat</p></div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div className="lg:col-span-5">
                        <form onSubmit={handleSubmit} className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-5">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-semibold text-slate-900 text-base">Formulir Pemesanan</h2>
                                <p className="text-xs text-slate-500 mt-0.5">Lengkapi detail informasi rapat di bawah ini.</p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Ruangan</label>
                                <select 
                                    value={formData.room_id} 
                                    onChange={(e) => setFormData({...formData, room_id: e.target.value})}
                                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 outline-none transition"
                                >
                                    <option value="">-- Pilih Ruangan Rapat --</option>
                                    {rooms.map((room) => (
                                        <option key={room.id} value={room.id}>{room.name} (Kapasitas: {room.capacity} Orang)</option>
                                    ))}
                                </select>
                                {formErrors.room_id && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.room_id}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Judul Rapat</label>
                                <input 
                                    type="text" 
                                    placeholder="Contoh: Sprint Planning Q3"
                                    value={formData.title} 
                                    onChange={(e) => setFormData({...formData, title: e.target.value})}
                                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 outline-none transition"
                                />
                                {formErrors.title && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.title}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tanggal Rapat</label>
                                <input 
                                    type="date" 
                                    value={formData.date} 
                                    onChange={(e) => setFormData({...formData, date: e.target.value})}
                                    className="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 outline-none transition"
                                />
                                {formErrors.date && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.date}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Jam Mulai</label>
                                    <input 
                                        type="text" 
                                        placeholder="09:00"
                                        value={formData.start_time} 
                                        onChange={(e) => setFormData({...formData, start_time: e.target.value})}
                                        className="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 outline-none transition"
                                    />
                                    {formErrors.start_time && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.start_time}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Jam Selesai</label>
                                    <input 
                                        type="text" 
                                        placeholder="11:00"
                                        value={formData.end_time} 
                                        onChange={(e) => setFormData({...formData, end_time: e.target.value})}
                                        className="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 outline-none transition"
                                    />
                                    {formErrors.end_time && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.end_time}</p>}
                                </div>
                            </div>
                            <p className="text-[11px] text-slate-400 -mt-3">* Gunakan format 24-jam standar (Contoh: 14:30)</p>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Catatan <span className="normal-case text-slate-400">(opsional)</span></label>
                                <textarea
                                    rows={3}
                                    placeholder="Agenda atau kebutuhan khusus ruangan"
                                    value={formData.notes}
                                    onChange={(e) => setFormData({...formData, notes: e.target.value})}
                                    className="w-full resize-none bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 p-2.5 outline-none transition"
                                />
                                {formErrors.notes && <p className="text-red-500 text-xs mt-1 font-medium">{formErrors.notes}</p>}
                            </div>

                            <button 
                                type="submit" 
                                disabled={submitting}
                                className="w-full bg-red-600 hover:bg-red-700 text-white font-medium text-sm py-2.5 rounded-lg shadow-sm transition disabled:opacity-50"
                            >
                                {submitting ? 'Memproses...' : 'Konfirmasi Pemesanan'}
                            </button>
                        </form>
                    </div>

                    <div className="lg:col-span-7">
                        <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                            <div className="border-b border-slate-100 pb-4 mb-4 flex flex-col gap-4 md:flex-row md:justify-between md:items-center">
                                <div>
                                    <h2 className="font-semibold text-slate-900 text-base">Daftar Jadwal Rapat</h2>
                                    <p className="text-xs text-slate-500 mt-0.5">Seluruh agenda rapat yang terdaftar dalam sistem.</p>
                                </div>
                                <div className="flex gap-2">
                                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari agenda..." className="w-36 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs outline-none focus:border-red-400" />
                                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs outline-none focus:border-red-400">
                                        <option value="all">Semua status</option>
                                        <option value="pending">Menunggu</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Dibatalkan</option>
                                    </select>
                                </div>
                            </div>

                            {filteredBookings.length === 0 ? (
                                <div className="text-center py-12 border-2 border-dashed border-slate-200 rounded-xl">
                                    <p className="text-slate-400 text-sm font-medium">Belum ada agenda yang cocok.</p>
                                    <p className="text-slate-400 text-xs mt-1">Ubah filter atau gunakan formulir untuk membuat jadwal baru.</p>
                                </div>
                            ) : (
                                <div className="space-y-3 max-h-[520px] overflow-y-auto pr-1">
                                    {filteredBookings.map((b) => (
                                        <div key={b.id} className="p-4 bg-white border border-slate-200 rounded-xl hover:border-slate-300 transition flex justify-between items-center shadow-xs">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2"><h3 className="font-bold text-slate-900 text-sm">{b.title}</h3><span className="text-[10px] font-semibold uppercase rounded-full bg-slate-100 px-2 py-1 text-slate-500">{b.status || 'pending'}</span></div>
                                                <div className="flex items-center space-x-3 text-xs text-slate-500">
                                                    <span>{formatBookingDate(b.start_time)}</span>
                                                    <span>•</span>
                                                    <span className="font-medium text-slate-700">{formatBookingTime(b.start_time)} - {formatBookingTime(b.end_time)}</span>
                                                </div>
                                                <p className="text-xs text-slate-400">{b.room?.name || 'Ruangan belum tersedia'}{b.room?.location ? ` · ${b.room.location}` : ''}</p>
                                            </div>
                                            {b.status !== 'rejected' && <button onClick={() => handleCancel(b.id)} className="bg-red-50 text-red-600 border border-red-200 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-100 transition">Batalkan</button>}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <section className="mt-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <div className="flex items-center justify-between mb-5">
                        <div><h2 className="font-semibold text-slate-900 text-base">Ruangan aktif</h2><p className="text-xs text-slate-500 mt-0.5">Kapasitas dan lokasi ruangan yang siap digunakan.</p></div>
                        <span className="text-xs font-semibold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full border border-emerald-200">{rooms.filter((room) => room.is_active).length} aktif</span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        {rooms.filter((room) => room.is_active).map((room) => (
                            <div key={room.id} className="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <div className="flex items-start justify-between gap-3"><h3 className="font-semibold text-slate-900 text-sm">{room.name}</h3><span className="h-2 w-2 rounded-full bg-emerald-500 mt-1" /></div>
                                <p className="text-xs text-slate-500 mt-3">{room.location || 'Lokasi belum diatur'}</p>
                                <p className="text-xs font-medium text-slate-700 mt-1">Kapasitas {room.capacity} orang</p>
                            </div>
                        ))}
                    </div>
                </section>
            </main>
        </div>
    );
}