'use client';

import { useEffect, useState } from 'react';
import { ApiError, createBooking, updateBooking } from '@/api';
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

const emptyForm: BookingFormValues = { room_id: '', title: '', participant_count: '1', date: '', start_time: '', end_time: '', notes: '' };

function valuesFromBooking(booking: Booking | null, date: string): BookingFormValues {
    if (!booking) return { ...emptyForm, date };
    const start = new Date(booking.start_time);
    const end = new Date(booking.end_time);
    return {
        room_id: String(booking.room_id),
        title: booking.title,
        participant_count: String(booking.participant_count),
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

    // Audit 496 Parameter USE (Issue #186): Menutup modal booking dengan tombol Escape untuk aksesibilitas keyboard
    useEffect(() => {
        if (!isOpen) return;
        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        // Audit 496 Parameter 52: Double-Submit Protection pada Tombol Konfirmasi Booking
        if (submitting) return;

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
                participant_count: Number(form.participant_count),
                start_time: `${form.date}T${form.start_time}:00+07:00`,
                end_time: `${form.date}T${form.end_time}:00+07:00`,
                notes: form.notes?.trim() || undefined
            };
            if (initialBooking) await updateBooking(initialBooking.id, payload);
            else await createBooking(payload);
            await onSaved();
            onClose();
        } catch (error: unknown) {
            if (error instanceof ApiError && Object.keys(error.fieldErrors).length > 0) {
                setErrors(error.fieldErrors);
            } else if (error && typeof error === 'object' && 'fieldErrors' in error && error.fieldErrors && typeof error.fieldErrors === 'object' && Object.keys(error.fieldErrors as Record<string, string>).length > 0) {
                setErrors(error.fieldErrors as Record<string, string>);
            } else {
                const message = error instanceof Error ? error.message : 'Gagal menyimpan booking';
                if (message.toLowerCase().includes('sudah dibooking') || message.toLowerCase().includes('bentrok')) {
                    setErrors({
                        form: message,
                        start_time: 'Jadwal ruangan sudah terisi pada rentang waktu ini.',
                        end_time: 'Silakan pilih rentang waktu lain.',
                    });
                } else {
                    setErrors({ form: message });
                }
            }
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
            <div role="status" aria-live="polite" className="sr-only">{submitting ? 'Sedang mengirim data booking ke server...' : ''}</div>
            <form onSubmit={handleSubmit} className="space-y-5">
                <div>
                    <label htmlFor="modal-room" className="mb-2 block text-sm font-semibold text-emerald-100">Pilih ruangan</label>
                    <select
                        id="modal-room"
                        value={form.room_id}
                        disabled={submitting}
                        aria-invalid={Boolean(errors.room_id || errors.room)}
                        aria-describedby={(errors.room_id || errors.room) ? 'modal-room-error' : undefined}
                        onChange={(event) => updateField('room_id', event.target.value)}
                        className={`w-full rounded-xl border ${errors.room_id || errors.room ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark] disabled:cursor-not-allowed disabled:opacity-60`}
                    >
                        <option value="">Pilih ruangan yang tersedia</option>
                        {rooms.map((room) => <option key={room.id} value={room.id}>{room.name} · {room.capacity} orang</option>)}
                    </select>
                    <FieldError id="modal-room-error" field="room_id" message={errors.room_id || errors.room} />
                </div>
                <div>
                    <label htmlFor="modal-title" className="mb-2 block text-sm font-semibold text-emerald-100">Nama agenda</label>
                    <input
                        id="modal-title"
                        value={form.title}
                        disabled={submitting}
                        aria-invalid={Boolean(errors.title || errors.name)}
                        aria-describedby={(errors.title || errors.name) ? 'modal-title-error' : undefined}
                        onChange={(event) => updateField('title', event.target.value)}
                        placeholder="Contoh: Weekly product sync"
                        className={`w-full rounded-xl border ${errors.title || errors.name ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60`}
                    />
                    <FieldError id="modal-title-error" field="title" message={errors.title || errors.name} />
                </div>
                <div>
                    <label htmlFor="modal-participants" className="mb-2 block text-sm font-semibold text-emerald-100">Jumlah peserta</label>
                    <input
                        id="modal-participants"
                        type="number"
                        min="1"
                        value={form.participant_count}
                        disabled={submitting}
                        aria-invalid={Boolean(errors.participant_count || errors.participants)}
                        aria-describedby={(errors.participant_count || errors.participants) ? 'modal-participants-error' : undefined}
                        onChange={(event) => updateField('participant_count', event.target.value)}
                        className={`w-full rounded-xl border ${errors.participant_count || errors.participants ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60`}
                    />
                    <FieldError id="modal-participants-error" field="participant_count" message={errors.participant_count || errors.participants} />
                </div>
                <div>
                    <label htmlFor="modal-date" className="mb-2 block text-sm font-semibold text-emerald-100">Tanggal</label>
                    <input
                        id="modal-date"
                        type="date"
                        min={new Date().toISOString().split('T')[0]}
                        value={form.date}
                        disabled={submitting}
                        aria-invalid={Boolean(errors.date)}
                        aria-describedby={errors.date ? 'modal-date-error' : undefined}
                        onChange={(event) => updateField('date', event.target.value)}
                        className={`w-full rounded-xl border ${errors.date ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark] disabled:cursor-not-allowed disabled:opacity-60`}
                    />
                    <FieldError id="modal-date-error" field="date" message={errors.date} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label htmlFor="modal-start" className="mb-2 block text-sm font-semibold text-emerald-100">Mulai</label>
                        <input
                            id="modal-start"
                            type="time"
                            value={form.start_time}
                            disabled={submitting}
                            aria-invalid={Boolean(errors.start_time)}
                            aria-describedby={errors.start_time ? 'modal-start-error' : undefined}
                            onChange={(event) => updateField('start_time', event.target.value)}
                            className={`w-full rounded-xl border ${errors.start_time ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark] disabled:cursor-not-allowed disabled:opacity-60`}
                        />
                        <FieldError id="modal-start-error" field="start_time" message={errors.start_time} />
                    </div>
                    <div>
                        <label htmlFor="modal-end" className="mb-2 block text-sm font-semibold text-emerald-100">Selesai</label>
                        <input
                            id="modal-end"
                            type="time"
                            value={form.end_time}
                            disabled={submitting}
                            aria-invalid={Boolean(errors.end_time)}
                            aria-describedby={errors.end_time ? 'modal-end-error' : undefined}
                            onChange={(event) => updateField('end_time', event.target.value)}
                            className={`w-full rounded-xl border ${errors.end_time ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white [color-scheme:dark] disabled:cursor-not-allowed disabled:opacity-60`}
                        />
                        <FieldError id="modal-end-error" field="end_time" message={errors.end_time} />
                    </div>
                </div>
                <div>
                    <label htmlFor="modal-notes" className="mb-2 block text-sm font-semibold text-emerald-100">Catatan <span className="font-normal text-emerald-200/50">(opsional)</span></label>
                    <textarea
                        id="modal-notes"
                        rows={3}
                        maxLength={500}
                        value={form.notes}
                        disabled={submitting}
                        aria-invalid={Boolean(errors.notes)}
                        aria-describedby={errors.notes ? 'modal-notes-error' : undefined}
                        onChange={(event) => updateField('notes', event.target.value)}
                        className={`w-full resize-none rounded-xl border ${errors.notes ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-emerald-800'} bg-emerald-950/60 px-3.5 py-3 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60`}
                    />
                    <FieldError id="modal-notes-error" field="notes" message={errors.notes} />
                </div>
                <div className="flex justify-end gap-3">
                    <button type="button" disabled={submitting} onClick={onClose} className="rounded-xl border border-emerald-800 px-4 py-3 text-sm font-semibold text-emerald-100 hover:bg-emerald-900/50 disabled:cursor-not-allowed disabled:opacity-50">Batal</button>
                    <button
                        type="submit"
                        disabled={submitting}
                        aria-busy={submitting}
                        data-loading={submitting}
                        data-submitting={submitting}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#f57c00] px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-[#d96800] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {submitting ? (
                            <>
                                <svg className="h-4 w-4 animate-spin text-slate-950" viewBox="0 0 24 24" fill="none">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                <span data-testid="submit-status-text">Menyimpan booking...</span>
                            </>
                        ) : initialBooking ? (
                            'Simpan perubahan'
                        ) : (
                            'Ajukan booking'
                        )}
                    </button>
                </div>
            </form>
        </div>
    </div>;
}

function FieldError({ id, field, message }: { id?: string; field?: string; message?: string }) {
    return message ? (
        <p
            id={id}
            role="alert"
            data-testid={id || (field ? `${field}-error` : undefined)}
            data-field-error={field}
            className="mt-1.5 text-xs font-semibold text-rose-400"
        >
            {message}
        </p>
    ) : null;
}
