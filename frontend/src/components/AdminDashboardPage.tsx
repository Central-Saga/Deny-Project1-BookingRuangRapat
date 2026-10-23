'use client';

import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { approveBooking, clearToken, createRoom, createUser, deleteRoom, getBookings, getRooms, getUsers, logout, rejectBooking, updateRoom, updateUser } from '@/services/api';
import type { Booking, Room, User } from '@/types';

type Section = 'overview' | 'approvals' | 'history' | 'rooms' | 'users';

const iconPaths = {
    grid: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>,
    check: <><path d="m5 12 4 4L19 6" /><circle cx="12" cy="12" r="9" /></>,
    door: <><path d="M4 21h16M6 21V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v17M14 12h.01" /></>,
    logout: <><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5" /></>,
    plus: <><path d="M12 5v14M5 12h14" /></>,
    refresh: <><path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4M4 13a8.1 8.1 0 0 0 15.5 2M20 19v-4h-4" /></>
};

function Icon({ name, className = 'h-5 w-5' }: { name: keyof typeof iconPaths; className?: string }) {
    return <svg aria-hidden="true" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{iconPaths[name]}</svg>;
}

function dateLabel(value: string) { return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }); }
function timeLabel(value: string) { return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); }

export default function AdminDashboardPage({ user }: { user: User }) {
    const router = useRouter();
    const [section, setSection] = useState<Section>('overview');
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [bookings, setBookings] = useState<Booking[]>([]);
    const [rooms, setRooms] = useState<Room[]>([]);
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [toast, setToast] = useState<string | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [rejectTarget, setRejectTarget] = useState<Booking | null>(null);
    const [approvalNotes, setApprovalNotes] = useState('');
    const [logoutConfirm, setLogoutConfirm] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);
    const [bookingSearch, setBookingSearch] = useState('');
    const [bookingDate, setBookingDate] = useState('');
    const [roomForm, setRoomForm] = useState({ name: '', capacity: '8', location: '', facilities: '', description: '', is_active: true });
    const [editingRoom, setEditingRoom] = useState<Room | null>(null);
    const [roomDeleteTarget, setRoomDeleteTarget] = useState<Room | null>(null);
    const [togglingRoomId, setTogglingRoomId] = useState<number | null>(null);
    const [deletingRoom, setDeletingRoom] = useState(false);
    const [userSearch, setUserSearch] = useState('');
    const [userRole, setUserRole] = useState<'all' | User['role']>('all');
    const [userStatus, setUserStatus] = useState<'all' | User['status']>('all');
    const [userForm, setUserForm] = useState({ name: '', email: '', password: '', role: 'karyawan' as User['role'], status: 'active' as User['status'] });
    const [creatingUser, setCreatingUser] = useState(false);
    const [userFormError, setUserFormError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    async function loadData() {
        try { setLoading(true); const [nextBookings, nextRooms, nextUsers] = await Promise.all([getBookings(), getRooms(), getUsers()]); setBookings(nextBookings); setRooms(nextRooms); setUsers(nextUsers); setError(null); }
        catch (loadError: unknown) { setError(loadError instanceof Error ? loadError.message : 'Gagal memuat data admin'); }
        finally { setLoading(false); }
    }

    useEffect(() => { void loadData(); }, []);

    async function handleLogout() { setLogoutConfirm(false); try { setLoggingOut(true); await logout(); } finally { clearToken(); router.replace('/'); } }

    async function approve(id: number) { try { setProcessingId(id); await approveBooking(id); setToast('Booking berhasil disetujui.'); await loadData(); } catch (actionError: unknown) { setToast(actionError instanceof Error ? actionError.message : 'Gagal menyetujui booking.'); } finally { setProcessingId(null); } }
    async function reject() { if (!rejectTarget || approvalNotes.trim().length < 10) return; try { setSaving(true); await rejectBooking(rejectTarget.id, approvalNotes.trim()); setRejectTarget(null); setApprovalNotes(''); setToast('Booking ditolak dan alasannya tersimpan.'); await loadData(); } catch (actionError: unknown) { setToast(actionError instanceof Error ? actionError.message : 'Gagal menolak booking.'); } finally { setSaving(false); } }
    async function saveRoom(event: FormEvent) { event.preventDefault(); try { setSaving(true); const payload = { name: roomForm.name.trim(), capacity: Number(roomForm.capacity), location: roomForm.location.trim(), facilities: roomForm.facilities.split(',').map((item) => item.trim()).filter(Boolean), description: roomForm.description.trim() || undefined, is_active: roomForm.is_active }; if (editingRoom) await updateRoom(editingRoom.id, payload); else await createRoom(payload); setEditingRoom(null); setRoomForm({ name: '', capacity: '8', location: '', facilities: '', description: '', is_active: true }); setToast('Ruangan berhasil disimpan.'); await loadData(); } catch (saveError: unknown) { setToast(saveError instanceof Error ? saveError.message : 'Gagal menyimpan ruangan.'); } finally { setSaving(false); } }
    function startEditRoom(room: Room) { setEditingRoom(room); setRoomForm({ name: room.name, capacity: String(room.capacity), location: room.location || '', facilities: (room.facilities || []).join(', '), description: room.description || '', is_active: room.is_active }); }
    function cancelEditRoom() { setEditingRoom(null); setRoomForm({ name: '', capacity: '8', location: '', facilities: '', description: '', is_active: true }); }
    async function toggleRoomActive(room: Room) { try { setTogglingRoomId(room.id); await updateRoom(room.id, { name: room.name, capacity: room.capacity, location: room.location || '', facilities: room.facilities, description: room.description || undefined, is_active: !room.is_active }); setToast(room.is_active ? 'Ruangan dinonaktifkan.' : 'Ruangan diaktifkan.'); await loadData(); } catch (toggleError: unknown) { setToast(toggleError instanceof Error ? toggleError.message : 'Gagal mengubah status ruangan.'); } finally { setTogglingRoomId(null); } }
    async function confirmDeleteRoom() { if (!roomDeleteTarget) return; try { setDeletingRoom(true); await deleteRoom(roomDeleteTarget.id); setRoomDeleteTarget(null); setToast('Ruangan berhasil dihapus.'); await loadData(); } catch (deleteError: unknown) { setToast(deleteError instanceof Error ? deleteError.message : 'Gagal menghapus ruangan.'); } finally { setDeletingRoom(false); } }
    async function changeUser(target: User, field: 'role' | 'status', value: User['role'] | User['status']) { try { const updated = await updateUser(target.id, { role: field === 'role' ? value as User['role'] : target.role, status: field === 'status' ? value as User['status'] : target.status }); setUsers((current) => current.map((item) => item.id === updated.id ? updated : item)); setToast('Akses pengguna diperbarui.'); } catch (saveError: unknown) { setToast(saveError instanceof Error ? saveError.message : 'Gagal memperbarui pengguna.'); } }
    async function createUserAccount(event: FormEvent) { event.preventDefault(); setUserFormError(null); try { setCreatingUser(true); const created = await createUser(userForm); setUsers((current) => [created, ...current]); setUserForm({ name: '', email: '', password: '', role: 'karyawan', status: 'active' }); setToast('Akun pengguna berhasil dibuat.'); await loadData(); } catch (createError: unknown) { setUserFormError(createError instanceof Error ? createError.message : 'Gagal membuat akun pengguna.'); } finally { setCreatingUser(false); } }

    const pending = useMemo(() => bookings.filter((booking) => booking.status === 'pending'), [bookings]);
    const archive = useMemo(() => bookings.filter((booking) => booking.status !== 'pending'), [bookings]);
    const approvalRows = useMemo(() => pending.filter((booking) => `${booking.title} ${booking.user?.name || ''} ${booking.room?.name || ''}`.toLowerCase().includes(bookingSearch.toLowerCase()) && (!bookingDate || booking.start_time.slice(0, 10) === bookingDate)), [bookingDate, bookingSearch, pending]);
    const historyRows = useMemo(() => archive.filter((booking) => `${booking.title} ${booking.user?.name || ''} ${booking.room?.name || ''}`.toLowerCase().includes(bookingSearch.toLowerCase()) && (!bookingDate || booking.start_time.slice(0, 10) === bookingDate)), [archive, bookingDate, bookingSearch]);
    const visibleUsers = useMemo(() => users.filter((target) => `${target.name} ${target.email}`.toLowerCase().includes(userSearch.toLowerCase()) && (userRole === 'all' || target.role === userRole) && (userStatus === 'all' || target.status === userStatus)), [userRole, userSearch, userStatus, users]);
    const activeRooms = rooms.filter((room) => room.is_active);
    const nav: { id: Section; label: string; icon: keyof typeof iconPaths }[] = [{ id: 'overview', label: 'Ringkasan', icon: 'grid' }, { id: 'approvals', label: 'Persetujuan booking', icon: 'check' }, { id: 'rooms', label: 'Kelola ruangan', icon: 'door' }, { id: 'users', label: 'Manajemen pengguna', icon: 'grid' }, { id: 'history', label: 'Riwayat booking', icon: 'refresh' }];

    return <div className="admin-shell min-h-screen bg-[#072418] text-white selection:bg-[#f57c00] selection:text-slate-950">
        <header className="sticky top-0 z-30 border-b border-emerald-800/60 bg-[#0a2e22]/90 shadow-xl shadow-black/30 backdrop-blur-md">
            <div className="mx-auto flex h-[76px] max-w-[1440px] items-center justify-between px-5 sm:px-8">
                <div className="flex items-center gap-3.5">
                    <button type="button" onClick={() => setSidebarOpen((open) => !open)} aria-label="Toggle navigasi" className="flex h-11 w-11 shrink-0 flex-col items-center justify-center gap-1.5 rounded-xl bg-[#f57c00] text-slate-950 shadow-md shadow-orange-950/20 transition-all duration-200 hover:bg-[#d96800] hover:scale-105 lg:-ml-4">
                        <span className="h-0.5 w-5 rounded-full bg-current" />
                        <span className="h-0.5 w-5 rounded-full bg-current" />
                        <span className="h-0.5 w-5 rounded-full bg-current" />
                    </button>
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#f57c00] to-[#e66000] text-sm font-black text-slate-950 shadow-lg shadow-orange-950/30">SM</div>
                    <div>
                        <p className="font-extrabold tracking-tight text-base">Saga MeetSpace</p>
                        <p className="hidden text-[11px] font-semibold text-emerald-400/80 sm:block">Panel admin ruang rapat</p>
                    </div>
                </div>
                <div className="flex items-center gap-3.5">
                    <div className="hidden text-right sm:block">
                        <p className="text-sm font-bold">{user.name}</p>
                        <p className="text-[11px] font-bold text-[#f59e0b]">Administrator</p>
                    </div>
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl border-2 border-[#f57c00]/50 bg-emerald-900/80 font-bold text-emerald-200 shadow-inner">{user.name.charAt(0).toUpperCase()}</div>
                    <button type="button" onClick={() => setLogoutConfirm(true)} aria-label="Keluar" className="rounded-xl p-2.5 text-emerald-300/70 transition-all duration-200 hover:bg-rose-500/15 hover:text-rose-400 hover:scale-105"><Icon name="logout" /></button>
                </div>
            </div>
        </header>

        <aside className={`fixed inset-x-0 top-[76px] z-20 border-b border-emerald-800/60 bg-[#0a2e22]/95 backdrop-blur-md transition-all duration-300 lg:bottom-0 lg:right-auto lg:border-b-0 lg:border-r ${sidebarOpen ? 'lg:w-[17rem]' : 'lg:w-24'}`}>
            <nav className="flex max-w-[1440px] flex-col gap-2 px-5 py-4 sm:px-8 lg:block lg:px-4 lg:py-8">
                {nav.map((item) => (
                    <button key={item.id} type="button" onClick={() => setSection(item.id)} title={item.label} className={`group flex w-full items-center gap-3.5 rounded-2xl px-4 py-3.5 text-left text-sm font-semibold transition-all duration-200 lg:mb-2.5 ${sidebarOpen ? '' : 'lg:justify-center lg:px-2'} ${section === item.id ? 'bg-[#f57c00] text-slate-950 shadow-lg shadow-orange-950/30 scale-[1.02]' : 'text-emerald-100/70 hover:bg-emerald-900/50 hover:text-white'}`}>
                        <span className={`transition-transform duration-200 ${section === item.id ? 'scale-110' : 'group-hover:scale-110'}`}><Icon name={item.icon} /></span>
                        <span className={sidebarOpen ? '' : 'lg:hidden'}>{item.label}</span>
                    </button>
                ))}
            </nav>
        </aside>

        {toast && (
            <div className="fixed right-6 top-24 z-50 flex items-center gap-3 rounded-2xl border border-emerald-500/40 bg-[#0d2822] px-5 py-4 text-sm font-semibold text-emerald-200 shadow-2xl shadow-black/60 backdrop-blur-xl animate-in fade-in slide-in-from-top-4 duration-300">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300">
                    <Icon name="check" className="h-4 w-4" />
                </span>
                <span className="flex-1">{toast}</span>
                <button onClick={() => setToast(null)} className="ml-2 text-emerald-400/60 hover:text-white">&times;</button>
            </div>
        )}

        {logoutConfirm && (
            <Modal title="Keluar dari panel admin?" icon="logout" onClose={() => setLogoutConfirm(false)}>
                <p className="text-sm leading-relaxed text-emerald-100/70">Sesi administrator Anda akan diakhiri dan dialihkan ke halaman utama.</p>
                <div className="mt-7 flex justify-end gap-3">
                    <button type="button" disabled={loggingOut} onClick={() => setLogoutConfirm(false)} className="rounded-xl border border-emerald-800 px-5 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50 disabled:opacity-50">Batal</button>
                    <button type="button" disabled={loggingOut} onClick={() => void handleLogout()} className="rounded-xl bg-[#f57c00] px-5 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-[#d96800] disabled:opacity-50">{loggingOut ? 'Memproses...' : 'Keluar'}</button>
                </div>
            </Modal>
        )}

        {rejectTarget && (
            <Modal title="Alasan penolakan" icon="door" tone="rose" onClose={() => setRejectTarget(null)}>
                <textarea autoFocus value={approvalNotes} onChange={(event) => setApprovalNotes(event.target.value)} minLength={10} className="min-h-32 w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-4 py-3 text-sm text-white outline-none focus:border-[#f57c00] transition" placeholder="Tuliskan alasan penolakan secara jelas (minimal 10 karakter)..." />
                <div className="mt-7 flex justify-end gap-3">
                    <button type="button" disabled={saving} onClick={() => setRejectTarget(null)} className="rounded-xl border border-emerald-800 px-5 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50 disabled:opacity-50">Batal</button>
                    <button type="button" disabled={saving || approvalNotes.trim().length < 10} onClick={() => void reject()} className="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-rose-700 disabled:opacity-50">{saving ? 'Menyimpan...' : 'Tolak booking'}</button>
                </div>
            </Modal>
        )}

        {roomDeleteTarget && (
            <Modal title="Hapus ruangan?" icon="door" tone="rose" onClose={() => setRoomDeleteTarget(null)}>
                <p className="text-sm leading-relaxed text-emerald-100/70">Ruangan <span className="font-bold text-white">{roomDeleteTarget.name}</span> akan dihapus permanen dari sistem dan tidak dapat dipesan kembali.</p>
                <div className="mt-7 flex justify-end gap-3">
                    <button type="button" disabled={deletingRoom} onClick={() => setRoomDeleteTarget(null)} className="rounded-xl border border-emerald-800 px-5 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-900/50 disabled:opacity-50">Batal</button>
                    <button type="button" disabled={deletingRoom} onClick={() => void confirmDeleteRoom()} className="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-rose-700 disabled:opacity-50">{deletingRoom ? 'Menghapus...' : 'Hapus ruangan'}</button>
                </div>
            </Modal>
        )}

        <main className={`mx-auto max-w-[1440px] px-5 pb-14 pt-28 sm:px-8 lg:pt-12 lg:pr-12 transition-all duration-300 ${sidebarOpen ? 'lg:pl-[19rem]' : 'lg:pl-32'}`}>
            {error && <div role="alert" className="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-300 shadow-lg"><p>{error}</p><button type="button" onClick={() => void loadData()} className="mt-3 rounded-lg bg-[#f57c00] px-4 py-2 text-xs font-bold text-slate-950">Coba lagi</button></div>}
            {loading && <p className="mb-6 rounded-2xl border border-emerald-800 bg-[#0d2822] px-5 py-4 text-sm text-emerald-100/70 shadow-lg">Memuat data sistem...</p>}

            {section === 'overview' && (
                <>
                    <div className="mb-12 grid grid-cols-1 items-center gap-10 lg:grid-cols-2">
                        <div>
                            <span className="mb-3 inline-block text-[11px] font-bold uppercase tracking-wider text-[#f59e0b]">DASHBOARD ADMIN</span>
                            <h1 className="text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-6xl">Kelola Booking, <span className="bg-gradient-to-r from-[#f57c00] to-[#f59e0b] bg-clip-text text-transparent">Kolaborasi Hebat</span></h1>
                            <p className="mt-6 max-w-xl text-sm leading-relaxed text-emerald-100/70 sm:text-base">Pantau permintaan ruang rapat secara real-time, kelola inventaris ruangan, dan bantu setiap tim bekerja dengan teratur dan efisien.</p>
                            <div className="mt-8 flex flex-wrap items-center gap-4">
                                <button type="button" onClick={() => setSection('approvals')} className="rounded-xl bg-[#f57c00] px-7 py-3.5 text-sm font-bold text-slate-950 shadow-xl shadow-orange-950/30 transition-all duration-200 hover:bg-[#d96800] hover:scale-105">Tinjau booking</button>
                                <span className="flex items-center gap-2.5 rounded-xl border border-emerald-800/80 bg-emerald-950/60 px-4 py-3 text-xs font-semibold text-emerald-300/80"><span className="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-400 shadow-sm shadow-emerald-400" /> Sistem aktif & siap digunakan</span>
                            </div>
                        </div>
                        <div className="relative overflow-hidden rounded-3xl border border-emerald-800/80 bg-[#0d2822]/80 p-3 shadow-2xl backdrop-blur-xl">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="h-52 rounded-2xl border border-emerald-800/50 bg-cover bg-center transition duration-500 hover:scale-105" style={{ backgroundImage: "url('https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&q=85&w=900')" }} />
                                <div className="h-52 rounded-2xl border border-emerald-800/50 bg-cover bg-center transition duration-500 hover:scale-105" style={{ backgroundImage: "url('https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&q=85&w=900')" }} />
                            </div>
                        </div>
                    </div>

                    <div className="mb-10 grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {[
                            ['MENUNGGU', pending.length, 'persetujuan admin'], 
                            ['DISETUJUI', bookings.filter((b) => b.status === 'approved').length, 'booking aktif'], 
                            ['RUANGAN TERSEDIA', activeRooms.length, 'siap dipesan'], 
                            ['TOTAL BOOKING', bookings.length, 'sepanjang waktu']
                        ].map(([label, value, sub]) => (
                            <div key={String(label)} className="group rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-6 shadow-xl transition-all duration-300 hover:border-[#f57c00]/50 hover:scale-[1.02]">
                                <p className="text-[11px] font-bold uppercase tracking-wider text-emerald-200/60">{label}</p>
                                <p className="mt-3 text-4xl font-extrabold text-white">{value}</p>
                                <p className="mt-2 text-xs font-medium text-emerald-200/40">{sub}</p>
                            </div>
                        ))}
                    </div>
                </>
            )}

            {section === 'approvals' && <BookingSection categoryLabel="PERSETUJUAN" title="Persetujuan booking" description="Tinjau dan kelola permintaan booking ruangan dari karyawan." bookings={approvalRows} search={bookingSearch} date={bookingDate} setSearch={setBookingSearch} setDate={setBookingDate} onApprove={approve} onReject={setRejectTarget} processingId={processingId} showActions empty="Tidak ada permintaan yang belum disetujui." />}
            {section === 'history' && <BookingSection categoryLabel="ARSIP" title="Riwayat booking" description="Pantau status dan jadwal rapat yang Anda buat." bookings={historyRows} search={bookingSearch} date={bookingDate} setSearch={setBookingSearch} setDate={setBookingDate} onApprove={approve} onReject={setRejectTarget} processingId={processingId} empty="Belum ada arsip booking." />}
            {section === 'rooms' && <RoomSection rooms={rooms} form={roomForm} saving={saving} editing={editingRoom} togglingRoomId={togglingRoomId} onChange={setRoomForm} onSubmit={saveRoom} onEdit={startEditRoom} onCancelEdit={cancelEditRoom} onToggleActive={toggleRoomActive} onDelete={setRoomDeleteTarget} />}
            {section === 'users' && <UserSection users={visibleUsers} search={userSearch} role={userRole} status={userStatus} onSearch={setUserSearch} onRole={setUserRole} onStatus={setUserStatus} onChange={changeUser} form={userForm} onFormChange={setUserForm} onCreate={createUserAccount} creating={creatingUser} formError={userFormError} />}
        </main>
    </div>;
}

function Modal({ title, icon, tone = 'amber', onClose, children }: { title: string; icon: keyof typeof iconPaths; tone?: 'amber' | 'rose'; onClose: () => void; children: React.ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-md animate-in fade-in duration-200">
            <div role="dialog" aria-modal="true" aria-labelledby="admin-modal-title" className="w-full max-w-md rounded-3xl border border-emerald-800 bg-[#0d2822] p-7 shadow-2xl shadow-black/60">
                <div className={`mb-5 flex h-12 w-12 items-center justify-center rounded-2xl ${tone === 'rose' ? 'bg-rose-500/20 text-rose-400' : 'bg-[#f57c00]/20 text-[#f59e0b]'}`}>
                    <Icon name={icon} className="h-6 w-6" />
                </div>
                <h2 id="admin-modal-title" className="text-xl font-bold text-white">{title}</h2>
                <div className="mt-3">{children}</div>
            </div>
        </div>
    );
}

function BookingSection({ categoryLabel, title, description, bookings, search, date, setSearch, setDate, onApprove, onReject, processingId = null, showActions = false, empty }: { categoryLabel: string; title: string; description: string; bookings: Booking[]; search: string; date: string; setSearch: (value: string) => void; setDate: (value: string) => void; onApprove: (id: number) => void; onReject: (booking: Booking) => void; processingId?: number | null; showActions?: boolean; empty: string }) { 
    return (
        <section className="rounded-3xl border border-emerald-800/80 bg-[#0a2e22]/50 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
            <div className="mb-6 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between border-b border-emerald-800/60 pb-6">
                <div>
                    <span className="text-[11px] font-bold uppercase tracking-wider text-[#f59e0b]">{categoryLabel}</span>
                    <h2 className="mt-1 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{title}</h2>
                    <p className="mt-1 text-sm text-emerald-100/70">{description}</p>
                </div>
                <div className="flex flex-wrap items-center gap-3 self-end lg:self-auto">
                    <div className="relative">
                        <span className="absolute inset-y-0 left-3 flex items-center pointer-events-none text-emerald-400/60">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </span>
                        <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari agenda" className="w-full sm:w-64 rounded-2xl border border-emerald-800/80 bg-[#072418] py-2.5 pl-10 pr-4 text-xs font-medium text-white placeholder-emerald-400/50 outline-none focus:border-[#f57c00] transition" />
                    </div>
                    <input type="date" value={date} onChange={(event) => setDate(event.target.value)} className="w-full sm:w-auto rounded-2xl border border-emerald-800/80 bg-[#072418] px-4 py-2.5 text-xs font-medium text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]" />
                </div>
            </div>
            {bookings.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-emerald-800/80 p-12 text-center text-emerald-100/60 bg-[#0d2822]/40">
                    <p className="text-base font-semibold text-white">Belum ada booking yang cocok</p>
                    <p className="mt-1 text-sm text-emerald-100/60">{empty}</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {bookings.map((booking) => (
                        <div key={booking.id} className="rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-6 shadow-xl transition-all duration-200 hover:border-emerald-600/60">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="text-base font-bold text-white">{booking.title}</h3>
                                    <p className="mt-1.5 text-xs font-medium text-emerald-100/70">{booking.user?.name || 'Pengguna'} · <span className="text-[#f59e0b] font-semibold">{booking.room?.name || 'Ruangan'}</span> · {dateLabel(booking.start_time)} · {timeLabel(booking.start_time)} - {timeLabel(booking.end_time)}</p>
                                    {booking.approval_notes && <p className="mt-2 inline-block rounded-lg border border-rose-500/20 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-300">Alasan admin: {booking.approval_notes}</p>}
                                    {booking.cancellation_reason && <p className="mt-2 inline-block rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-300">Alasan pembatalan: {booking.cancellation_reason}</p>}
                                </div>
                                {showActions && (
                                    <div className="flex gap-2.5">
                                        <button disabled={processingId === booking.id} onClick={() => onReject(booking)} className="rounded-xl border border-rose-500/30 px-4 py-2.5 text-xs font-bold text-rose-300 transition hover:bg-rose-500/15 disabled:opacity-50">Tolak</button>
                                        <button disabled={processingId === booking.id} onClick={() => onApprove(booking.id)} className="rounded-xl bg-[#f57c00] px-5 py-2.5 text-xs font-bold text-slate-950 shadow-md shadow-orange-950/20 transition hover:bg-[#d96800] disabled:opacity-50">{processingId === booking.id ? 'Memproses...' : 'Setujui'}</button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    ); 
}

function RoomSection({ rooms, form, saving, editing, togglingRoomId, onChange, onSubmit, onEdit, onCancelEdit, onToggleActive, onDelete }: { rooms: Room[]; form: { name: string; capacity: string; location: string; facilities: string; description: string; is_active: boolean }; saving: boolean; editing: Room | null; togglingRoomId: number | null; onChange: (value: typeof form) => void; onSubmit: (event: FormEvent) => void; onEdit: (room: Room) => void; onCancelEdit: () => void; onToggleActive: (room: Room) => void; onDelete: (room: Room) => void }) {
    return (
        <section className="rounded-3xl border border-emerald-800/80 bg-[#0a2e22]/50 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
            <div className="mb-6 flex items-center justify-between border-b border-emerald-800/60 pb-6">
                <div>
                    <span className="text-[11px] font-bold uppercase tracking-wider text-[#f59e0b]">INVENTARIS</span>
                    <h2 className="mt-1 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{editing ? `Edit ruangan: ${editing.name}` : 'Kelola ruangan'}</h2>
                    <p className="mt-1 text-sm text-emerald-100/70">Tambah, ubah, atau nonaktifkan fasilitas ruang rapat.</p>
                </div>
                {editing && <button type="button" onClick={onCancelEdit} className="rounded-xl border border-emerald-800 px-4 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-900/50">Batal edit</button>}
            </div>
            <form onSubmit={onSubmit} className="mb-8 grid gap-4 rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-6 shadow-xl sm:grid-cols-2">
                <input required placeholder="Nama ruangan" value={form.name} onChange={(event) => onChange({ ...form, name: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <input required type="number" min="1" placeholder="Kapasitas orang" value={form.capacity} onChange={(event) => onChange({ ...form, capacity: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <input required placeholder="Lokasi ruangan" value={form.location} onChange={(event) => onChange({ ...form, location: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <input placeholder="Fasilitas (pisahkan dengan koma)" value={form.facilities} onChange={(event) => onChange({ ...form, facilities: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <button type="submit" disabled={saving} className="rounded-2xl bg-[#f57c00] px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-orange-950/20 transition hover:bg-[#d96800] disabled:opacity-60 sm:col-span-2">{saving ? 'Menyimpan...' : editing ? 'Simpan perubahan' : 'Tambah ruangan baru'}</button>
            </form>
            <div className="space-y-4">
                {rooms.length === 0 && <p className="rounded-xl border border-dashed border-emerald-700 px-4 py-6 text-center text-sm text-emerald-100/70">Belum ada ruangan terdaftar.</p>}
                {rooms.map((room) => (
                    <div key={room.id} className="flex flex-col justify-between gap-4 rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-5 shadow-xl transition hover:border-emerald-600/60 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-3">
                                <span className="text-base font-bold text-white">{room.name}</span>
                                <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-[11px] font-bold uppercase tracking-wider ${room.is_active ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-300' : 'border border-rose-500/30 bg-rose-500/15 text-rose-300'}`}>
                                    <span className={`h-1.5 w-1.5 rounded-full ${room.is_active ? 'bg-emerald-400' : 'bg-rose-400'}`} />
                                    {room.is_active ? 'Aktif' : 'Nonaktif'}
                                </span>
                            </div>
                            <p className="text-xs font-medium text-emerald-100/70">
                                <span className="font-semibold text-[#f59e0b]">{room.capacity} orang</span> · <span className="text-emerald-200">{room.location}</span>
                            </p>
                            {room.facilities && (
                                <p className="mt-1 text-xs text-emerald-100/60">
                                    <span className="font-semibold text-emerald-300">Fasilitas:</span> {Array.isArray(room.facilities) ? room.facilities.join(', ') : room.facilities}
                                </p>
                            )}
                        </div>
                        <div className="flex gap-4 border-t border-emerald-800/50 pt-2 text-xs font-bold sm:border-t-0 sm:pt-0">
                            <button type="button" onClick={() => onEdit(room)} className="text-[#f59e0b] hover:underline">Edit</button>
                            <button type="button" disabled={togglingRoomId === room.id} onClick={() => onToggleActive(room)} className="text-emerald-200 hover:underline disabled:opacity-50">{togglingRoomId === room.id ? 'Memproses...' : room.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>
                            <button type="button" onClick={() => onDelete(room)} className="text-rose-400 hover:underline">Hapus</button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function UserSection({ users, search, role, status, onSearch, onRole, onStatus, onChange, form, onFormChange, onCreate, creating, formError }: { users: User[]; search: string; role: 'all' | User['role']; status: 'all' | User['status']; onSearch: (value: string) => void; onRole: (value: 'all' | User['role']) => void; onStatus: (value: 'all' | User['status']) => void; onChange: (user: User, field: 'role' | 'status', value: User['role'] | User['status']) => void; form: { name: string; email: string; password: string; role: User['role']; status: User['status'] }; onFormChange: (value: typeof form) => void; onCreate: (event: FormEvent) => void; creating: boolean; formError: string | null }) {
    return (
        <section className="rounded-3xl border border-emerald-800/80 bg-[#0a2e22]/50 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
            <div className="mb-6 border-b border-emerald-800/60 pb-6">
                <span className="text-[11px] font-bold uppercase tracking-wider text-[#f59e0b]">PENGELOLAAN AKSES</span>
                <h2 className="mt-1 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Manajemen pengguna</h2>
                <p className="mt-1 text-sm text-emerald-100/70">Kelola akun karyawan dan hak akses administrator.</p>
            </div>
            <form onSubmit={onCreate} className="mb-8 grid gap-4 rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-6 shadow-xl sm:grid-cols-2">
                <input required placeholder="Nama lengkap" value={form.name} onChange={(event) => onFormChange({ ...form, name: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <input required type="email" placeholder="Alamat email" value={form.email} onChange={(event) => onFormChange({ ...form, email: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <input required type="password" minLength={8} placeholder="Password (minimal 8 karakter)" value={form.password} onChange={(event) => onFormChange({ ...form, password: event.target.value })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition [color-scheme:dark]" />
                <select value={form.role} onChange={(event) => onFormChange({ ...form, role: event.target.value as User['role'] })} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]">
                    <option value="karyawan">Karyawan</option>
                    <option value="admin">Admin</option>
                </select>
                {formError && <p role="alert" className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs font-medium text-rose-300 sm:col-span-2">{formError}</p>}
                <button type="submit" disabled={creating} className="rounded-2xl bg-[#f57c00] px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-orange-950/20 transition hover:bg-[#d96800] disabled:opacity-60 sm:col-span-2">{creating ? 'Membuat akun...' : 'Buat akun pengguna'}</button>
            </form>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Cari nama atau email..." className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white placeholder-emerald-200/40 outline-none focus:border-[#f57c00] transition" />
                <select value={role} onChange={(event) => onRole(event.target.value as 'all' | User['role'])} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]">
                    <option value="all">Semua role</option>
                    <option value="admin">Admin</option>
                    <option value="karyawan">Karyawan</option>
                </select>
                <select value={status} onChange={(event) => onStatus(event.target.value as 'all' | User['status'])} className="w-full rounded-2xl border border-emerald-800 bg-emerald-950/60 px-4 py-3.5 text-sm text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]">
                    <option value="all">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
            <div className="space-y-4">
                {users.length === 0 && <p className="rounded-xl border border-dashed border-emerald-700 px-4 py-6 text-center text-sm text-emerald-100/70">Tidak ada pengguna yang cocok.</p>}
                {users.map((target) => (
                    <div key={target.id} className="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-emerald-800/80 bg-[#0d2822] p-5 shadow-xl transition hover:border-emerald-600/60">
                        <div>
                            <p className="text-base font-bold text-white">{target.name}</p>
                            <p className="mt-0.5 text-xs text-emerald-100/60">{target.email}</p>
                        </div>
                        <div className="flex gap-3">
                            <select value={target.role} onChange={(event) => onChange(target, 'role', event.target.value as User['role'])} className="rounded-xl border border-emerald-800 bg-emerald-950/60 px-3 py-2 text-xs font-semibold text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]">
                                <option value="karyawan">Karyawan</option>
                                <option value="admin">Admin</option>
                            </select>
                            <select value={target.status} onChange={(event) => onChange(target, 'status', event.target.value as User['status'])} className="rounded-xl border border-emerald-800 bg-emerald-950/60 px-3 py-2 text-xs font-semibold text-white outline-none focus:border-[#f57c00] transition [color-scheme:dark]">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}