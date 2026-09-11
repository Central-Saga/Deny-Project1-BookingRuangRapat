'use client';

import { useEffect, useState } from 'react';
import { createBooking, updateBooking } from '@/services/api';
import { bookingSchema, BookingFormValues } from '@/lib/validations/bookingSchema';
import { Booking, Room } from '@/types';

interface BookingModalProps {
    isOpen: boolean;
    rooms: Room[];
    initialBooking: Booking | null;
    initialDate: string;
    onClose: () => void;
    onSaved: () => Promise<void>;
}

const emptyForm: BookingFormValues = { room_id: '', title: '', date: '', start_time: '', end_time: '', notes: '' };

function valuesFromBooking(booking: Booking | null, date: string): BookingFormValues {
    if (!booking) return { ...emptyForm, date };
    const start = new Date(booking.start_time);
    const end = new Date(booking.end_time);
    return {
        room_id: String(booking.room_id),
        title: booking.title,
        date: start.toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' }),
        start_time: start.toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' }),
        end_time: end.toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' }),
        notes: booking.notes || ''
    };
}

export default function BookingModal({ isOpen, rooms, initialBooking, initialDate, onClose, onSaved }: BookingModalProps) {
    const [form, setForm] = useState<BookingFormValues>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setForm(valuesFromBooking(initialBooking, initialDate));
            setErrors({});
        }
    }, [initialBooking, initialDate, isOpen]);

    if (!isOpen) return null;

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        const result = bookingSchema.safeParse(form);
        if (!result.success) {
            const nextErrors: Record<string, string> = {};
            result.error.issues.forEach((issue) => { if (issue.path[0]) nextErrors[String(issue.path[0])] = issue.message; });
            setErrors(nextErrors);
            return;
        }

        try {
            setSubmitting(true);
            const payload = {
                room_id: Number(form.room_id),
                title: form.title.trim(),
                start_time: `${form.date}T${form.start_time}:00+07:00`,
                end_time: `${form.date}T${form.end_time}:00+07:00`,
                notes: form.notes?.trim() || undefined
            };
            if (initialBooking) await updateBooking(initialBooking.id, payload);
            else await createBooking(payload);
            await onSaved();
            onClose();
        } catch (error) {
            setErrors({ form: error instanceof Error ? error.message : 'Gagal menyimpan booking' });
        } finally {
            setSubmitting(false);
        }
    }

    function updateField(field: keyof BookingFormValues, value: string) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="booking-modal-title">
        <div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-emerald-800 bg-[#0d2822] p-6 shadow-2xl sm:p-8">
            <div className="mb-6 flex items-start justify-between gap-4 border-b border-emerald-800 pb-5">
                <div><p className="mb-2 text-xs font-bold uppercase tracking-wider text-[#f59e0b]">{initialBooking ? 'Edit booking' : 'Booking baru'}</p><h2 id="booking-modal-title" className="text-2xl font-bold text-white">{initialBooking ? 'Perbarui booking' : 'Pesan ruang rapat'}</h2></div>
                <button type="button" onClick={onClose} aria-label="Tutup form booking" className="rounded-lg p-2 text-emerald-200/70 hover:bg-emerald-900/60 hover:text-white">&times;</button>
            </div>
            {errors.form && <div role="alert" className="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{errors.form}</div>}
            <form onSubmit={handleSubmit} className="space-y-5">
                <div><label htmlFor="modal-room" className="mb-2 block text-sm font-semibold text-emerald-100">Pilih ruangan</label><select id="modal-room" value={form.room_id} onChange={(event) => updateField('room_id', event.target.value)} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark]"><option value="">Pilih ruangan yang tersedia</option>{rooms.map((room) => <option key={room.id} value={room.id}>{room.name} · {room.capacity} orang</option>)}</select><FieldError message={errors.room_id} /></div>
                <div><label htmlFor="modal-title" className="mb-2 block text-sm font-semibold text-emerald-100">Nama agenda</label><input id="modal-title" value={form.title} onChange={(event) => updateField('title', event.target.value)} placeholder="Contoh: Weekly product sync" className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white" /><FieldError message={errors.title} /></div>
                <div><label htmlFor="modal-date" className="mb-2 block text-sm font-semibold text-emerald-100">Tanggal</label><input id="modal-date" type="date" min={new Date().toISOString().split('T')[0]} value={form.date} onChange={(event) => updateField('date', event.target.value)} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark]" /><FieldError message={errors.date} /></div>
                <div className="grid grid-cols-2 gap-3"><div><label htmlFor="modal-start" className="mb-2 block text-sm font-semibold text-emerald-100">Mulai</label><input id="modal-start" type="time" value={form.start_time} onChange={(event) => updateField('start_time', event.target.value)} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark]" /><FieldError message={errors.start_time} /></div><div><label htmlFor="modal-end" className="mb-2 block text-sm font-semibold text-emerald-100">Selesai</label><input id="modal-end" type="time" value={form.end_time} onChange={(event) => updateField('end_time', event.target.value)} className="w-full rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark]" /><FieldError message={errors.end_time} /></div></div>
                <div><label htmlFor="modal-notes" className="mb-2 block text-sm font-semibold text-emerald-100">Catatan <span className="font-normal text-emerald-200/50">(opsional)</span></label><textarea id="modal-notes" rows={3} maxLength={500} value={form.notes} onChange={(event) => updateField('notes', event.target.value)} className="w-full resize-none rounded-xl border border-emerald-800 bg-emerald-950/60 px-3.5 py-3 text-sm text-white" /><FieldError message={errors.notes} /></div>
                <div className="flex justify-end gap-3"><button type="button" onClick={onClose} className="rounded-xl border border-emerald-800 px-4 py-3 text-sm font-semibold text-emerald-100 hover:bg-emerald-900/50">Batal</button><button type="submit" disabled={submitting} className="rounded-xl bg-[#f57c00] px-5 py-3 text-sm font-bold text-slate-950 hover:bg-[#d96800] disabled:cursor-not-allowed disabled:opacity-60">{submitting ? 'Menyimpan booking...' : initialBooking ? 'Simpan perubahan' : 'Ajukan booking'}</button></div>
            </form>
        </div>
    </div>;
}

function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1.5 text-xs font-medium text-amber-300">{message}</p> : null;
}
