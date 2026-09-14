'use client';

import { useMemo, useState } from 'react';
import { Booking } from '@/types';

interface BookingCalendarProps {
    bookings: Booking[];
    loading?: boolean;
    onSelectDate: (date: string) => void;
    onSelectBooking: (booking: Booking) => void;
}

function dateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function addDays(date: Date, days: number): Date {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}

function bookingDateKey(value: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date(value));
}

function isSelectableDate(key: string, today: string): boolean {
    const date = new Date(`${key}T00:00:00`);
    return key >= today && date.getDay() !== 0 && date.getDay() !== 6;
}

export default function BookingCalendar({ bookings, loading = false, onSelectDate, onSelectBooking }: BookingCalendarProps) {
    const [month, setMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    const today = dateKey(new Date());
    const days = useMemo(() => {
        const firstDay = new Date(month.getFullYear(), month.getMonth(), 1);
        const mondayOffset = (firstDay.getDay() + 6) % 7;
        const gridStart = addDays(firstDay, -mondayOffset);
        return Array.from({ length: 42 }, (_, index) => addDays(gridStart, index));
    }, [month]);

    const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(month);
    const groupedBookings = useMemo(() => bookings.reduce<Record<string, Booking[]>>((groups, booking) => {
        const key = bookingDateKey(booking.start_time);
        groups[key] = groups[key] || [];
        groups[key].push(booking);
        return groups;
    }, {}), [bookings]);

    return (
        <section id="jadwal-booking" className="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-6 shadow-sm backdrop-blur sm:p-8">
            <div className="mb-6 flex flex-col justify-between gap-4 border-b border-emerald-900 pb-5 sm:flex-row sm:items-center">
                <div>
                    <p className="mb-2 text-xs font-bold uppercase tracking-wider text-[#f59e0b]">Kalender jadwal</p>
                    <h2 className="text-2xl font-bold text-white">Jadwal booking ruangan</h2>
                    <p className="mt-1 text-sm text-emerald-100/70">Pilih tanggal untuk membuat booking atau buka event untuk melihat detail.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button type="button" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))} aria-label="Bulan sebelumnya" className="rounded-lg border border-emerald-800 px-3 py-2 text-emerald-200 hover:bg-emerald-900/50">&larr;</button>
                    <p className="min-w-36 text-center text-sm font-bold capitalize text-white">{monthLabel}</p>
                    <button type="button" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))} aria-label="Bulan berikutnya" className="rounded-lg border border-emerald-800 px-3 py-2 text-emerald-200 hover:bg-emerald-900/50">&rarr;</button>
                    <button type="button" onClick={() => { const current = new Date(); setMonth(new Date(current.getFullYear(), current.getMonth(), 1)); onSelectDate(today); }} className="rounded-lg bg-[#f57c00] px-3 py-2 text-xs font-bold text-slate-950 hover:bg-[#d96800]">Hari ini</button>
                </div>
            </div>
            <div className="mb-2 grid grid-cols-7 text-center text-[11px] font-bold uppercase tracking-wider text-emerald-200/60">
                {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((day) => <span key={day} className="py-2">{day}</span>)}
            </div>
            {loading && <div className="mb-3 grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-emerald-900 bg-emerald-900/40" aria-label="Memuat kalender"><span className="col-span-7 h-8 animate-pulse bg-emerald-900/40" /><span className="col-span-7 h-8 animate-pulse bg-emerald-900/25" /><span className="col-span-7 h-8 animate-pulse bg-emerald-900/40" /></div>}
            {!loading && bookings.length === 0 && <div className="mb-3 rounded-xl border border-dashed border-emerald-800 bg-emerald-950/20 px-6 py-8 text-center"><div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#f57c00]/15 text-2xl">&#128197;</div><p className="text-sm font-semibold text-white">Tidak ada agenda rapat pada tanggal ini</p><p className="mt-1 text-xs text-emerald-100/60">Pilih tanggal kerja untuk membuat booking baru.</p></div>}
            <div className="grid grid-cols-7 overflow-hidden rounded-xl border border-emerald-900">
                {days.map((day) => {
                    const key = dateKey(day);
                    const dayBookings = groupedBookings[key] || [];
                    const inMonth = day.getMonth() === month.getMonth();
                    const selectable = isSelectableDate(key, today);
                    return <button key={key} type="button" onClick={() => onSelectDate(key)} className={`min-h-28 border-b border-r border-emerald-900 p-2 text-left align-top transition hover:bg-emerald-900/50 ${inMonth ? 'bg-emerald-950/20' : 'bg-emerald-950/50 opacity-45'} ${!selectable && key >= today ? 'opacity-60' : ''}`}>
                        <span className={`mb-1 inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${key === today ? 'bg-[#f57c00] text-slate-950' : 'text-emerald-100'}`}>{day.getDate()}</span>
                        <span className="block space-y-1">
                            {dayBookings.slice(0, 3).map((booking) => <span key={booking.id} role="button" tabIndex={0} onClick={(event) => { event.stopPropagation(); onSelectBooking(booking); }} onKeyDown={(event) => { if (event.key === 'Enter') onSelectBooking(booking); }} className={`block truncate rounded px-1.5 py-1 text-[10px] font-semibold ${booking.status === 'approved' ? 'bg-emerald-500/25 text-emerald-200' : booking.status === 'pending' ? 'bg-amber-500/25 text-amber-200' : 'bg-slate-700/60 text-slate-300'}`}>{booking.title}</span>)}
                            {dayBookings.length > 3 && <span className="block px-1 text-[10px] text-emerald-200/60">+{dayBookings.length - 3} lainnya</span>}
                        </span>
                    </button>;
                })}
            </div>
        </section>
    );
}
