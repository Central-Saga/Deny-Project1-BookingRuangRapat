'use client';

import { useEffect, useMemo, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cancelBooking, clearToken, createBooking, deleteBooking, getAvailability, getBookings, getCurrentUser, getRooms, login, logout, saveToken, updateBooking } from '@/services/api';
import { Booking, Room, User } from '@/types';
import { bookingSchema } from '@/lib/validations/bookingSchema';
import BookingCalendar from '@/components/BookingCalendar';
import BookingModal from '@/components/BookingModal';

const OPERATING_START_MINUTES = 8 * 60;
const OPERATING_END_MINUTES = 17 * 60;

const statusLabels: Record<Booking['status'], string> = { pending: 'Menunggu persetujuan', approved: 'Disetujui', rejected: 'Dibatalkan' };

function Icon({ name, className = 'h-5 w-5' }: { name: 'calendar' | 'clock' | 'door' | 'logout' | 'plus' | 'search' | 'users' | 'refresh'; className?: string }) {
    const paths = {
        calendar: <><rect x="3" y="4" width="18" height="17" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></>,
        clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
        door: <><path d="M4 21h16M6 21V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v17M14 12h.01" /></>,
        logout: <><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5" /></>,
        plus: <><path d="M12 5v14M5 12h14" /></>,
        search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
        users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></>,
        refresh: <><path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4M4 13a8.1 8.1 0 0 0 15.5 2M20 19v-4h-4" /></>
    };
    return <svg aria-hidden="true" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1.5 text-xs font-medium text-amber-300">{message}</p> : null;
}

export default function Dashboard() {
    const [user, setUser] = useState<User | null>(null);
    const [authLoading, setAuthLoading] = useState(true);
    const [loginData, setLoginData] = useState({ email: '', password: '' });
    const [loginError, setLoginError] = useState<string | null>(null);
    const [loggingIn, setLoggingIn] = useState(false);
    const [rooms, setRooms] = useState<Room[]>([]);
    const [bookings, setBookings] = useState<Booking[]>([]);
    const [availabilityBookings, setAvailabilityBookings] = useState<Booking[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [deleteTarget, setDeleteTargetState] = useState<Booking | null>(null);
    const [cancelTarget, setCancelTarget] = useState<Booking | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [conflictError, setConflictError] = useState<string | null>(null);
    const [calendarNotice, setCalendarNotice] = useState<string | null>(null);
    const [logoutConfirm, setLogoutConfirm] = useState(false);
    const [bookingModalOpen, setBookingModalOpen] = useState(false);
        const [editingBooking, setEditingBooking] = useState<Booking | null>(null);
        const [availabilityDate, setAvailabilityDate] = useState(new Date().toISOString().split('T')[0]);
        const [availabilityRoomId, setAvailabilityRoomId] = useState('all');
    const [formData, setFormData] = useState({ room_id: '', title: '', date: '', start_time: '', end_time: '', notes: '' });
    const pathname = usePathname();
    const currentPage = pathname === '/' ? 'dashboard' : pathname.slice(1);
    const showDashboard = currentPage === 'dashboard';
    const showRooms = currentPage === 'ruangan';
    const showSchedule = currentPage === 'jadwal';
    const showHistory = currentPage === 'riwayat';

    const activeRooms = useMemo(() => rooms.filter((room) => room.is_active), [rooms]);
    const activeBookings = useMemo(() => bookings.filter((booking) => booking.status !== 'rejected'), [bookings]);
    const filteredBookings = useMemo(() => bookings.filter((booking) => {
        const matchesStatus = statusFilter === 'all' || booking.status === statusFilter;
        const searchable = `${booking.title} ${booking.room?.name || ''}`.toLowerCase();
        return matchesStatus && searchable.includes(search.toLowerCase());
    }), [bookings, search, statusFilter]);
    const today = new Date().toISOString().split('T')[0];
    const availabilityForDay = useMemo(() => availabilityBookings.filter((booking) => {
        const date = new Date(booking.start_time).toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
        return date === availabilityDate && (availabilityRoomId === 'all' || String(booking.room_id) === availabilityRoomId);
    }), [availabilityBookings, availabilityDate, availabilityRoomId]);

    function setDeleteTarget(target: Booking | null) {
        if (target === null || target.status === 'rejected') {
            setDeleteTargetState(target);
        }
    }

    function formatDate(value: string) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatTime(value: string) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '-' : date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function jakartaMinutes(value: string) {
        const date = new Date(value);
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(date);
        return Number(parts.find((part) => part.type === 'hour')?.value || 0) * 60 + Number(parts.find((part) => part.type === 'minute')?.value || 0);
    }

    function isRoomFullyBooked(roomBookings: Booking[]) {
        const intervals = roomBookings
            .map((booking) => [
                Math.max(OPERATING_START_MINUTES, jakartaMinutes(booking.start_time)),
                Math.min(OPERATING_END_MINUTES, jakartaMinutes(booking.end_time))
            ] as const)
            .filter(([start, end]) => end > start)
            .sort(([firstStart], [secondStart]) => firstStart - secondStart);

        let coveredUntil = OPERATING_START_MINUTES;
        for (const [start, end] of intervals) {
            if (start > coveredUntil) return false;
            coveredUntil = Math.max(coveredUntil, end);
            if (coveredUntil >= OPERATING_END_MINUTES) return true;
        }

        return false;
    }

    function errorMessage(errorValue: unknown, fallback: string) {
        return errorValue instanceof Error ? errorValue.message : fallback;
    }

    function toJakartaDateTime(date: string, time: string) {
        return `${date}T${time}:00+07:00`;
    }

    async function loadData() {
        try {
            setLoading(true);
            const [roomsData, bookingsData, availabilityData] = await Promise.all([getRooms(), getBookings(), getAvailability()]);
            setRooms(roomsData);
            setBookings(bookingsData);
            setAvailabilityBookings(availabilityData);
            setError(null);
        } catch (loadError: unknown) {
            setError(errorMessage(loadError, 'Gagal memuat data dari server'));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        async function initializeAuth() {
            const token = window.localStorage.getItem('meetspace_auth_token');
            if (!token) {
                setAuthLoading(false);
                return;
            }
            try {
                setUser(await getCurrentUser());
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

    async function handleLogin(event: FormEvent) {
        event.preventDefault();
        setLoginError(null);
        setLoggingIn(true);
        try {
            const result = await login(loginData.email, loginData.password);
            saveToken(result.token);
            setLogoutConfirm(false);
            setUser(result.user);
            await loadData();
        } catch (loginFailure: unknown) {
            setLoginError(errorMessage(loginFailure, 'Email atau password belum sesuai.'));
        } finally {
            setLoggingIn(false);
        }
    }

    function handleLogout() {
        setLogoutConfirm(true);
    }

    async function confirmLogout() {
        try { await logout(); } finally { setLogoutConfirm(false); clearToken(); setUser(null); setRooms([]); setBookings([]); setAvailabilityBookings([]); }
    }

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setFormErrors({});
        const result = bookingSchema.safeParse(formData);
        if (!result.success) {
            const errors: Record<string, string> = {};
            result.error.issues.forEach((issue) => { if (issue.path[0]) errors[issue.path[0] as string] = issue.message; });
            setFormErrors(errors);
            return;
        }
        try {
            setSubmitting(true);
            const payload = { room_id: Number(formData.room_id), title: formData.title.trim(), start_time: toJakartaDateTime(formData.date, formData.start_time), end_time: toJakartaDateTime(formData.date, formData.end_time), notes: formData.notes.trim() || undefined };
            if (editingBooking) {
                await updateBooking(editingBooking.id, payload);
            } else {
                await createBooking(payload);
            }
            setEditingBooking(null);
            setFormData({ room_id: '', title: '', date: '', start_time: '', end_time: '', notes: '' });
            await loadData();
        } catch (submitError: unknown) {
            const message = errorMessage(submitError, 'Gagal membuat pemesanan');
            if (message.toLowerCase().includes('sudah dibooking') || message.toLowerCase().includes('bentrok')) {
                setConflictError(message);
            } else {
                setFormErrors({ form: message });
            }
        } finally {
            setSubmitting(false);
        }
    }

    function startEditing(booking: Booking) {
        if (booking.status === 'rejected') return;
        const start = new Date(booking.start_time);
        const end = new Date(booking.end_time);
        const date = start.toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
        const day = new Date(`${date}T00:00:00`).getDay();
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        if (new Date(`${date}T00:00:00`) < todayDate) {
            setCalendarNotice('Booking yang sudah lewat tidak dapat diubah.');
            return;
        }
        if (day === 0 || day === 6) {
            setCalendarNotice('Booking pada hari Sabtu dan Minggu tidak dapat diubah.');
            return;
        }
        const time = (value: Date) => value.toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' });
        setEditingBooking(booking);
        setBookingModalOpen(true);
        setFormErrors({});
        setFormData({ room_id: String(booking.room_id), title: booking.title, date, start_time: time(start), end_time: time(end), notes: booking.notes || '' });
    }

    function cancelEditing() {
        setEditingBooking(null);
        setBookingModalOpen(false);
        setFormErrors({});
        setFormData({ room_id: '', title: '', date: '', start_time: '', end_time: '', notes: '' });
    }

    function openBookingModal(date = availabilityDate) {
        const selectedDate = new Date(`${date}T00:00:00`);
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        if (selectedDate < todayDate) {
            setCalendarNotice('Tanggal yang sudah lewat tidak dapat digunakan untuk membuat booking.');
            return;
        }
        if (selectedDate.getDay() === 0 || selectedDate.getDay() === 6) {
            setCalendarNotice('Booking hanya dapat dilakukan pada hari Senin sampai Jumat.');
            return;
        }
        setEditingBooking(null);
        setFormErrors({});
        setFormData({ room_id: '', title: '', date, start_time: '', end_time: '', notes: '' });
        setBookingModalOpen(true);
    }

    function handleCancel(booking: Booking) {
        setActionError(null);
        setCancelTarget(booking);
    }

    function handleCalendarDateSelect(date: string) {
        setAvailabilityDate(date);
        openBookingModal(date);
    }

    async function confirmCancel() {
        if (!cancelTarget) return;
        try {
            setActionError(null);
            await cancelBooking(cancelTarget.id);
            setCancelTarget(null);
            await loadData();
        } catch (cancelError: unknown) {
            setActionError(errorMessage(cancelError, 'Gagal membatalkan pemesanan'));
        }
    }

    async function confirmDelete() {
        if (!deleteTarget) return;
        try {
            setActionError(null);
            await deleteBooking(deleteTarget.id);
            setDeleteTarget(null);
            await loadData();
        } catch (deleteError: unknown) {
            setActionError(errorMessage(deleteError, 'Gagal menghapus pemesanan'));
        }
    }

    if (authLoading) return <div className="flex min-h-screen items-center justify-center bg-[#0b3524] text-sm font-medium text-emerald-100">Memeriksa sesi...</div>;

    if (!user) return (
        <main className="flex min-h-screen items-center justify-center bg-[#0b3524] px-4 py-8 sm:px-6">
            <div className="grid w-full max-w-5xl overflow-hidden rounded-[28px] border border-emerald-900 bg-[#0b3524] shadow-[0_24px_70px_rgba(0,0,0,0.4)] lg:grid-cols-[1.05fr_0.95fr]">
                <div className="relative hidden overflow-hidden bg-[#0b3524] p-12 text-white lg:block">
                    <div className="absolute -right-20 -top-20 h-72 w-72 rounded-full border-[28px] border-emerald-300/10" />
                    <div className="relative flex h-full flex-col justify-between">
                        <div>
                            <div className="mb-20 flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#f57c00] font-black text-slate-950">SM</div>
                                <span className="text-lg font-bold tracking-tight">Saga MeetSpace</span>
                            </div>
                            <p className="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#f57c00]">Akses ruang rapat</p>
                            <h1 className="max-w-md text-4xl font-bold leading-tight tracking-tight text-white">Booking Cepat, <span className="text-[#f57c00]">Kolaborasi Hebat</span></h1>
                            <p className="mt-5 max-w-sm text-sm leading-6 text-emerald-100/70">Temukan ruangan yang tepat, susun jadwal rapat, dan jaga kolaborasi tim tetap berjalan lancar.</p>
                        </div>
                        <div className="flex gap-8 border-t border-emerald-800 pt-6 text-sm text-emerald-100/70">
                            <span><strong className="block text-2xl text-white">24/7</strong>Akses booking</span>
                            <span><strong className="block text-2xl text-white">1</strong>Dashboard terpusat</span>
                        </div>
                    </div>
                </div>
                <div className="bg-[#0b3524] p-7 text-white sm:p-12">
                    <div className="mb-12 flex items-center gap-3 lg:hidden">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f57c00] font-black text-slate-950">SM</div>
                        <span className="font-bold text-white">Saga MeetSpace</span>
                    </div>
                    <div className="mb-8">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-[#f57c00]">Portal akses</p>
                        <h2 className="text-3xl font-bold tracking-tight text-white">Selamat datang kembali</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">Masuk untuk mengatur jadwal dan ruang rapat Anda.</p>
                    </div>
                    {loginError && <div role="alert" className="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{loginError}</div>}
                    <form onSubmit={handleLogin} className="space-y-5">
                        <div>
                            <label htmlFor="email" className="mb-2 block text-sm font-semibold text-emerald-100">Email</label>
                            <input id="email" type="email" required autoComplete="email" value={loginData.email} onChange={(event) => setLoginData({ ...loginData, email: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-[#f57c00] focus:ring-4 focus:ring-[#f57c00]/20" placeholder="nama@perusahaan.com" />
                        </div>
                        <div>
                            <label htmlFor="password" className="mb-2 block text-sm font-semibold text-emerald-100">Password</label>
                            <input id="password" type="password" required autoComplete="current-password" value={loginData.password} onChange={(event) => setLoginData({ ...loginData, password: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-[#f57c00] focus:ring-4 focus:ring-[#f57c00]/20" placeholder="Masukkan password Anda" />
                        </div>
                        <button type="submit" disabled={loggingIn} className="w-full rounded-xl bg-[#f57c00] px-4 py-3 text-sm font-bold text-slate-950 transition hover:bg-[#d96800] disabled:opacity-60">{loggingIn ? 'Memverifikasi...' : 'Masuk'}</button>
                    </form>
                    <p className="mt-7 text-center text-xs leading-5 text-emerald-200/50">Gunakan akun yang telah didaftarkan oleh perusahaan.</p>
                </div>
            </div>
        </main>
    );

    if (loading) return <div className="flex min-h-screen items-center justify-center bg-[#0d2822] text-sm font-medium text-emerald-100">Menyiapkan dashboard karyawan...</div>;
    if (error) return <div className="flex min-h-screen items-center justify-center bg-[#0b3524] p-6"><div className="w-full max-w-md rounded-2xl border border-rose-500/30 bg-[#0b3524] p-8 text-center shadow-sm text-white"><h1 className="text-lg font-bold">Data belum dapat dimuat</h1><p className="mt-2 text-sm text-emerald-100/70">{error}</p><button onClick={loadData} className="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#f57c00] px-4 py-2.5 text-sm font-semibold text-slate-950"><Icon name="refresh" className="h-4 w-4" /> Coba lagi</button></div></div>;

    return (
        <div className="min-h-screen bg-[#0b3524] text-white selection:bg-[#f57c00] selection:text-slate-950">
            {conflictError && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div role="dialog" aria-modal="true" aria-labelledby="conflict-title" className="w-full max-w-md rounded-2xl border border-emerald-800 bg-[#0b3524] p-6 shadow-2xl">
                        <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-full bg-[#f57c00]/20 text-[#f57c00]"><Icon name="clock" className="h-5 w-5" /></div>
                        <h2 id="conflict-title" className="text-lg font-bold text-white">Jadwal ruangan bentrok</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">Ruangan sudah digunakan pada waktu tersebut. Pilih jam lain atau gunakan waktu setelah rapat sebelumnya selesai.</p>
                        <p className="mt-3 rounded-lg bg-emerald-950/60 px-3 py-2 text-xs text-emerald-100/80">{conflictError}</p>
                        <div className="mt-6 flex justify-end"><button type="button" onClick={() => setConflictError(null)} className="rounded-xl bg-[#f57c00] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800]">Mengerti</button></div>
                    </div>
                </div>
            )}
            {calendarNotice && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div role="alertdialog" aria-modal="true" aria-labelledby="calendar-notice-title" className="w-full max-w-md rounded-2xl border border-emerald-800 bg-[#0b3524] p-6 shadow-2xl">
                        <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-full bg-[#f57c00]/20 text-[#f57c00]"><Icon name="calendar" className="h-5 w-5" /></div>
                        <h2 id="calendar-notice-title" className="text-lg font-bold text-white">Tanggal tidak tersedia</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">{calendarNotice}</p>
                        <div className="mt-6 flex justify-end"><button type="button" onClick={() => setCalendarNotice(null)} className="rounded-xl bg-[#f57c00] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800]">Mengerti</button></div>
                    </div>
                </div>
            )}
            {user && logoutConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div role="dialog" aria-modal="true" aria-labelledby="logout-title" className="w-full max-w-md rounded-2xl border border-emerald-800 bg-[#0b3524] p-6 shadow-2xl">
                        <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-full bg-[#f57c00]/20 text-[#f57c00]"><Icon name="logout" className="h-5 w-5" /></div>
                        <h2 id="logout-title" className="text-lg font-bold text-white">Keluar dari akun?</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">Anda akan keluar dari dashboard dan perlu masuk kembali untuk mengatur booking.</p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => setLogoutConfirm(false)} className="rounded-xl border border-emerald-800 px-4 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50">Tetap masuk</button>
                            <button type="button" onClick={confirmLogout} className="rounded-xl bg-[#f57c00] px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800]">Ya, keluar</button>
                        </div>
                    </div>
                </div>
            )}
            {cancelTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div role="dialog" aria-modal="true" aria-labelledby="cancel-title" className="w-full max-w-md rounded-2xl border border-emerald-800 bg-[#0d2822] p-6 shadow-2xl">
                        <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-full bg-amber-500/20 text-[#f59e0b]"><Icon name="calendar" className="h-5 w-5" /></div>
                        <h2 id="cancel-title" className="text-lg font-bold text-white">Batalkan booking?</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">Jadwal <span className="font-semibold text-white">{cancelTarget.title}</span> akan ditandai sebagai dibatalkan.</p>
                        {actionError && <p role="alert" className="mt-4 rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-medium text-rose-300">{actionError}</p>}
                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => { setCancelTarget(null); setActionError(null); }} className="rounded-xl border border-emerald-800 px-4 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50">Kembali</button>
                            <button type="button" onClick={confirmCancel} className="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700">Batalkan booking</button>
                        </div>
                    </div>
                </div>
            )}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <div role="dialog" aria-modal="true" aria-labelledby="delete-title" className="w-full max-w-md rounded-2xl border border-emerald-800 bg-[#0d2822] p-6 shadow-2xl">
                        <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-full bg-rose-500/20 text-rose-400">
                            <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5" /></svg>
                        </div>
                        <h2 id="delete-title" className="text-lg font-bold text-white">Hapus booking?</h2>
                        <p className="mt-2 text-sm leading-6 text-emerald-100/70">Booking <span className="font-semibold text-white">{deleteTarget.title}</span> akan dihapus dari jadwal Anda.</p>
                        {actionError && <p role="alert" className="mt-4 rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-medium text-rose-300">{actionError}</p>}
                        <div className="mt-6 flex justify-end gap-3">
                            <button type="button" onClick={() => { setDeleteTarget(null); setActionError(null); }} className="rounded-xl border border-emerald-800 px-4 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50">Batal</button>
                            <button type="button" onClick={confirmDelete} className="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-rose-700">Hapus booking</button>
                        </div>
                    </div>
                </div>
            )}
            
            <BookingModal
                isOpen={bookingModalOpen}
                rooms={activeRooms}
                initialBooking={editingBooking}
                initialDate={availabilityDate}
                onClose={cancelEditing}
                onSaved={loadData}
            />

            <header className="sticky top-0 z-20 border-b border-emerald-900/80 bg-[#0d2822]/95 backdrop-blur">
                <div className="mx-auto flex h-[72px] max-w-[1440px] items-center justify-between px-5 sm:px-8">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f57c00] text-sm font-black text-slate-950">SM</div>
                        <div>
                            <p className="font-bold tracking-tight text-white">Saga MeetSpace</p>
                            <p className="hidden text-[11px] text-emerald-200/50 sm:block">Portal ruang rapat karyawan</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-bold text-white">{user.name}</p>
                            <p className="text-xs text-[#f57c00]">Karyawan</p>
                        </div>
                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-900 text-sm font-bold text-emerald-200 border border-emerald-700">{user.name.charAt(0).toUpperCase()}</div>
                        <button type="button" onClick={handleLogout} aria-label="Keluar dari akun" title="Keluar" className="ml-1 rounded-lg p-2 text-emerald-300/70 transition hover:bg-rose-500/20 hover:text-rose-400"><Icon name="logout" className="h-5 w-5" /></button>
                    </div>
                </div>
            </header>

            <aside className="fixed inset-x-0 top-[72px] z-10 border-b border-emerald-900/80 bg-[#0d2822]/95 backdrop-blur lg:bottom-0 lg:right-auto lg:w-64 lg:border-b-0 lg:border-r">
                <nav aria-label="Navigasi utama" className="mx-auto flex max-w-[1440px] gap-2 overflow-x-auto px-5 py-3 sm:px-8 lg:block lg:px-4 lg:py-8">
                    {[
                        { href: '/dashboard', label: 'Beranda', icon: 'calendar' as const, active: showDashboard },
                        { href: '/ruangan', label: 'Ruangan Tersedia', icon: 'door' as const, active: showRooms },
                        { href: '/jadwal', label: 'Jadwal Booking', icon: 'clock' as const, active: showSchedule },
                        { href: '/riwayat', label: 'Riwayat Booking', icon: 'refresh' as const, active: showHistory },
                    ].map((item) => (
                        <Link key={item.href} href={item.href} className={`flex shrink-0 items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition lg:mb-2 ${item.active ? 'bg-[#f57c00] text-slate-950' : 'text-emerald-100/70 hover:bg-emerald-900/60 hover:text-white'}`}>
                            <Icon name={item.icon} className="h-5 w-5" />
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </aside>

            <main id="beranda" className="mx-auto max-w-[1440px] px-5 pb-8 pt-24 sm:px-8 lg:pl-72 lg:pt-12 lg:pr-12">
                {showDashboard && <>
                <div className="mb-12 grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                    <div>
                        <p className="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-[#f57c00]">Dashboard karyawan</p>
                        <h1 className="text-4xl sm:text-6xl font-bold tracking-tight text-white leading-tight">Booking Cepat, <span className="text-[#f57c00]">Kolaborasi Hebat</span></h1>
                        <p className="mt-6 text-sm sm:text-base leading-relaxed text-emerald-100/70 max-w-xl">Pesan ruang rapat dalam beberapa langkah dan atur agenda tim dengan lebih mudah, cepat, dan terorganisir.</p>
                        <div className="mt-8 flex items-center gap-4">
                            <button type="button" onClick={() => openBookingModal()} className="rounded-xl bg-[#f57c00] px-6 py-3.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800] shadow-lg">Booking Cepat</button>
                            <span className="text-xs text-emerald-200/50 flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span> Sistem aktif & siap digunakan</span>
                        </div>
                    </div>
                    <div className="relative">
                        <div className="overflow-hidden rounded-2xl border border-emerald-800 bg-emerald-950/40 p-2 shadow-2xl">
                            <div className="grid grid-cols-2 gap-2">
                                <div className="h-48 rounded-xl bg-cover bg-center border border-emerald-800/50" style={{ backgroundImage: `url('https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&q=85&w=900')` }}></div>
                                <div className="h-48 rounded-xl bg-cover bg-center border border-emerald-800/50" style={{ backgroundImage: `url('https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&q=85&w=900')` }}></div>
                            </div>
                        </div>
                    </div>
                </div>

                <section className="mb-10 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div className="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-5 shadow-sm backdrop-blur">
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-200/60">Agenda aktif</p>
                        <p className="mt-3 text-3xl font-bold text-white">{activeBookings.length}</p>
                        <p className="mt-1 text-xs text-emerald-200/50">booking Anda</p>
                    </div>
                    <div className="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-5 shadow-sm backdrop-blur">
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-200/60">Menunggu</p>
                        <p className="mt-3 text-3xl font-bold text-[#f59e0b]">{bookings.filter((booking) => booking.status === 'pending').length}</p>
                        <p className="mt-1 text-xs text-emerald-200/50">persetujuan admin</p>
                    </div>
                    <div className="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-5 shadow-sm backdrop-blur">
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-200/60">Ruangan tersedia</p>
                        <p className="mt-3 text-3xl font-bold text-white">{activeRooms.length}</p>
                        <p className="mt-1 text-xs text-emerald-200/50">siap dipesan</p>
                    </div>
                    <div className="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-5 shadow-sm backdrop-blur">
                        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-200/60">Total booking</p>
                        <p className="mt-3 text-3xl font-bold text-white">{bookings.length}</p>
                        <p className="mt-1 text-xs text-emerald-200/50">sepanjang waktu</p>
                    </div>
                </section>
                </>}

                {showRooms && <section id="ruangan-tersedia" className="mb-10 rounded-2xl border border-emerald-900 bg-emerald-950/30 p-6 shadow-sm backdrop-blur sm:p-8">
                    <div className="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div><p className="mb-2 text-xs font-bold uppercase tracking-wider text-[#f57c00]">Ruangan tersedia</p><h2 className="text-2xl font-bold text-white">Ruangan & ketersediaan</h2><p className="mt-1 text-sm text-emerald-100/70">Lihat kapasitas ruangan dan jam yang sudah terisi sebelum membuat booking.</p></div>
                        <div className="flex flex-wrap gap-2"><input type="date" value={availabilityDate} min={today} onChange={(event) => setAvailabilityDate(event.target.value)} className="rounded-lg border border-emerald-800 bg-emerald-950/60 px-3 py-2 text-xs text-white outline-none focus:border-[#f57c00] [color-scheme:dark]" /><select value={availabilityRoomId} onChange={(event) => setAvailabilityRoomId(event.target.value)} className="rounded-lg border border-emerald-800 bg-emerald-950/60 px-3 py-2 text-xs text-white outline-none focus:border-[#f57c00]"><option value="all" className="bg-[#0b3524]">Semua ruangan</option>{activeRooms.map((room) => <option key={room.id} value={room.id} className="bg-[#0b3524]">{room.name}</option>)}</select></div>
                    </div>
                    <div className="mb-7 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">{activeRooms.map((room) => { const roomBookings = availabilityForDay.filter((booking) => booking.room_id === room.id); const isFull = isRoomFullyBooked(roomBookings); return <div key={room.id} className="rounded-xl border border-emerald-900 bg-emerald-900/20 p-4 transition hover:border-[#f57c00]/50 hover:bg-emerald-900/40"><div className="mb-4 flex items-center justify-between"><span className={`rounded-lg p-2 ${isFull ? 'bg-rose-500/10 text-rose-300' : 'bg-emerald-500/10 text-emerald-300'}`}><Icon name="door" className="h-5 w-5" /></span><span className={`text-[11px] font-semibold ${isFull ? 'text-rose-300' : 'text-emerald-400'}`}>{isFull ? 'Full' : 'Tersedia'}</span></div><h3 className="font-bold text-white">{room.name}</h3><div className="mt-2 flex items-center gap-2 text-xs text-emerald-100/70"><span>{room.location || 'Lokasi belum diatur'}</span><span className="text-emerald-700">|</span><span className="flex items-center gap-1"><Icon name="users" className="h-3.5 w-3.5" />{room.capacity}</span></div></div>; })}</div>
                    <div className="mb-4 border-t border-emerald-900 pt-6"><p className="text-sm font-semibold text-white">Timeline penggunaan ruangan</p><p className="mt-1 text-xs text-emerald-100/60">Blok oranye menandakan jam yang sudah terisi.</p></div>
                    <div className="mb-3 flex items-center gap-4 text-[11px] text-emerald-100/60"><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#f57c00]" /> Terisi</span><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm border border-dashed border-emerald-400" /> Kosong</span></div>
                    {availabilityForDay.length === 0 && <div className="mb-3 rounded-lg border border-dashed border-emerald-800 bg-emerald-950/20 px-3 py-2 text-xs text-emerald-100/60">Belum ada booking pada tanggal ini. Semua baris ruangan masih kosong.</div>}
                    <div className="overflow-x-auto"><div className="min-w-[720px]"><div className="mb-2 ml-28 flex justify-between text-[10px] font-semibold text-emerald-100/60"><span>08.00</span><span>10.00</span><span>12.00</span><span>14.00</span><span>16.00</span><span>17.00</span></div><div className="space-y-3">{(availabilityRoomId === 'all' ? activeRooms : activeRooms.filter((room) => String(room.id) === availabilityRoomId)).map((room) => { const roomBookings = availabilityForDay.filter((booking) => booking.room_id === room.id); return <div key={room.id} className="flex items-center gap-3"><div className="w-25 shrink-0 truncate text-xs font-semibold text-white">{room.name}</div><div className="relative h-12 flex-1 rounded-lg border border-dashed border-emerald-700 bg-emerald-950/30"><div className="absolute inset-0 grid grid-cols-5 divide-x divide-emerald-800/60" />{roomBookings.length === 0 && <span className="absolute left-3 top-3 text-[10px] text-emerald-100/40">Belum terisi</span>}{roomBookings.map((booking) => { const start = Math.max(OPERATING_START_MINUTES, jakartaMinutes(booking.start_time)); const end = Math.min(OPERATING_END_MINUTES, jakartaMinutes(booking.end_time)); const left = ((start - OPERATING_START_MINUTES) / (OPERATING_END_MINUTES - OPERATING_START_MINUTES)) * 100; const width = Math.max(((end - start) / (OPERATING_END_MINUTES - OPERATING_START_MINUTES)) * 100, 2); return <div key={booking.id} title={`${booking.title} · ${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}`} className="absolute top-1.5 h-9 overflow-hidden rounded-md bg-[#f57c00] px-2 py-1 text-[10px] font-bold text-slate-950 shadow-sm" style={{ left: `${left}%`, width: `${width}%` }}><span className="block truncate">{booking.title}</span><span className="block text-[9px] font-medium">{formatTime(booking.start_time)}–{formatTime(booking.end_time)}</span></div>; })}</div></div>; })}</div></div></div>
                </section>}

                {showSchedule && <BookingCalendar bookings={availabilityBookings} onSelectDate={handleCalendarDateSelect} onSelectBooking={startEditing} />}

                {showHistory && <div className="mt-8 grid grid-cols-1 gap-8">
                    <section id="booking-form" className="hidden rounded-2xl border border-emerald-900 bg-emerald-950/30 p-6 shadow-sm backdrop-blur sm:p-8">
                        <div className="mb-6 border-b border-emerald-900 pb-5">
                            <p className="mb-2 text-xs font-bold uppercase tracking-wider text-[#f59e0b]">{editingBooking ? 'Edit booking' : 'Booking baru'}</p>
                            <div className="flex items-start justify-between gap-3"><div><h2 className="text-2xl font-bold text-white">{editingBooking ? 'Perbarui booking' : 'Pesan ruang rapat'}</h2><p className="mt-1 text-sm text-emerald-100/70">{editingBooking ? 'Sesuaikan detail agenda yang sudah dibuat.' : 'Isi detail agenda yang ingin Anda adakan.'}</p></div>{editingBooking && <button type="button" onClick={cancelEditing} className="rounded-lg border border-emerald-800 px-3 py-1.5 text-xs font-semibold text-emerald-200 hover:bg-emerald-900/50">Batal edit</button>}</div>
                        </div>
                        {formErrors.form && <div role="alert" className="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{formErrors.form}</div>}
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label htmlFor="room" className="mb-2 block text-sm font-semibold text-emerald-100">Pilih ruangan</label>
                                <select id="room" value={formData.room_id} onChange={(event) => setFormData({ ...formData, room_id: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20">
                                    <option value="" className="bg-[#0d2822]">Pilih ruangan yang tersedia</option>
                                    {activeRooms.map((room) => <option key={room.id} value={room.id} className="bg-[#0d2822]">{room.name} · {room.capacity} orang</option>)}
                                </select>
                                <FieldError message={formErrors.room_id} />
                            </div>
                            <div>
                                <label htmlFor="title" className="mb-2 block text-sm font-semibold text-emerald-100">Nama agenda</label>
                                <input id="title" type="text" placeholder="Contoh: Weekly product sync" value={formData.title} onChange={(event) => setFormData({ ...formData, title: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20" />
                                <FieldError message={formErrors.title} />
                            </div>
                            <div>
                                <label htmlFor="date" className="mb-2 block text-sm font-semibold text-emerald-100">Tanggal</label>
                                <input id="date" type="date" min={today} value={formData.date} onChange={(event) => setFormData({ ...formData, date: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20 [color-scheme:dark]" />
                                <FieldError message={formErrors.date} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label htmlFor="start_time" className="mb-2 block text-sm font-semibold text-emerald-100">Mulai</label>
                                    <input id="start_time" type="time" value={formData.start_time} onChange={(event) => setFormData({ ...formData, start_time: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20 [color-scheme:dark]" />
                                    <FieldError message={formErrors.start_time} />
                                </div>
                                <div>
                                    <label htmlFor="end_time" className="mb-2 block text-sm font-semibold text-emerald-100">Selesai</label>
                                    <input id="end_time" type="time" value={formData.end_time} onChange={(event) => setFormData({ ...formData, end_time: event.target.value })} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20 [color-scheme:dark]" />
                                    <FieldError message={formErrors.end_time} />
                                </div>
                            </div>
                            <div>
                                <label htmlFor="notes" className="mb-2 block text-sm font-semibold text-emerald-100">Catatan <span className="font-normal text-emerald-200/50">(opsional)</span></label>
                                <textarea id="notes" rows={3} maxLength={500} placeholder="Tambahkan kebutuhan khusus atau agenda rapat" value={formData.notes} onChange={(event) => setFormData({ ...formData, notes: event.target.value })} className="w-full resize-none rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white outline-none focus:border-[#f59e0b] focus:ring-4 focus:ring-[#f59e0b]/20" />
                                <div className="mt-1 text-right text-[11px] text-emerald-200/50">{formData.notes.length}/500</div>
                                <FieldError message={formErrors.notes} />
                            </div>
                            <button type="submit" disabled={submitting} className="w-full rounded-xl bg-[#f57c00] py-3.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800] disabled:opacity-60">{submitting ? 'Menyimpan booking...' : editingBooking ? 'Simpan perubahan' : 'Ajukan booking'}</button>
                        </form>
                    </section>

                    <section id="riwayat-booking" className="min-w-0 rounded-2xl border border-emerald-900 bg-emerald-950/30 p-6 shadow-sm backdrop-blur sm:p-8">
                        <div className="mb-6 flex flex-col justify-between gap-4 border-b border-emerald-900 pb-5 lg:flex-row lg:items-center">
                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-[#f59e0b]">Aktivitas Anda</p>
                                <h2 className="text-2xl font-bold text-white">Riwayat booking</h2>
                                <p className="mt-1 text-sm text-emerald-100/70">Pantau status dan jadwal rapat yang Anda buat.</p>
                            </div>
                            <div className="flex w-full gap-2 sm:w-auto">
                                <div className="relative min-w-0 flex-1 sm:w-44">
                                    <Icon name="search" className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-emerald-200/50" />
                                    <input aria-label="Cari booking" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari agenda" className="w-full rounded-lg border border-emerald-800 bg-emerald-950/60 py-2 pl-9 pr-3 text-xs text-white outline-none focus:border-[#f59e0b]" />
                                </div>
                                <select aria-label="Filter status" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="rounded-lg border border-emerald-800 bg-emerald-950/60 px-2 py-2 text-xs text-white outline-none focus:border-[#f59e0b]">
                                    <option value="all" className="bg-[#0d2822]">Semua</option>
                                    <option value="pending" className="bg-[#0d2822]">Menunggu</option>
                                    <option value="approved" className="bg-[#0d2822]">Disetujui</option>
                                    <option value="rejected" className="bg-[#0d2822]">Dibatalkan</option>
                                </select>
                            </div>
                        </div>
                        {filteredBookings.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-emerald-800 py-16 text-center">
                                <p className="text-sm font-semibold text-white">Belum ada booking yang cocok</p>
                                <p className="mt-1 text-xs text-emerald-100/50">Buat booking baru atau ubah filter pencarian.</p>
                            </div>
                        ) : (
                            <div className="max-h-[590px] space-y-3 overflow-y-auto pr-1">
                                {filteredBookings.map((booking) => (
                                    <div key={booking.id} className="rounded-xl border border-emerald-900 bg-emerald-900/20 p-4 transition hover:border-[#f59e0b]/50 hover:bg-emerald-900/40">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                                    <h3 className="truncate text-sm font-bold text-white">{booking.title}</h3>
                                                    <span className={`rounded-full px-2 py-1 text-[10px] font-bold ${booking.status === 'approved' ? 'bg-emerald-500/20 text-emerald-300' : booking.status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-700/50 text-slate-400'}`}>{statusLabels[booking.status]}</span>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-emerald-100/70">
                                                    <span className="font-medium text-white">{formatDate(booking.start_time)}</span>
                                                    <span className="hidden text-emerald-700 sm:inline">|</span>
                                                    <span>{formatTime(booking.start_time)} - {formatTime(booking.end_time)}</span>
                                                </div>
                                                <p className="mt-2 text-xs text-emerald-200/50">{booking.room?.name || 'Ruangan belum tersedia'}{booking.room?.location ? ` · ${booking.room.location}` : ''}</p>
                                                {booking.notes && <p className="mt-2 border-l-2 border-[#f57c00]/70 pl-2 text-xs leading-5 text-emerald-100/70"><span className="font-semibold text-[#f57c00]">Catatan:</span> {booking.notes}</p>}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {booking.status !== 'rejected' && <button type="button" onClick={() => startEditing(booking)} className="rounded-lg border border-emerald-800 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-200 transition hover:border-[#f57c00]/50 hover:bg-[#f57c00]/10 hover:text-[#f57c00]">Edit</button>}
                                                {booking.status !== 'rejected' && <button type="button" onClick={() => handleCancel(booking)} className="rounded-lg border border-emerald-800 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-200 transition hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-300">Batalkan</button>}
                                                <button type="button" onClick={() => setDeleteTarget(booking)} aria-label={`Hapus booking ${booking.title}`} className="rounded-lg border border-emerald-800 p-1.5 text-emerald-300/60 transition hover:border-rose-500/40 hover:bg-rose-500/10 hover:text-rose-400" title="Hapus booking"><svg aria-hidden="true" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5" /></svg></button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>}

            </main>
        </div>
    );
}