import { z } from 'zod';

export const bookingSchema = z.object({
    room_id: z.string().min(1, 'Pilih ruangan terlebih dahulu'),
    title: z.string().min(3, 'Judul rapat minimal 3 karakter'),
    date: z.string().min(1, 'Pilih tanggal rapat'),
    start_time: z.string().regex(/^([01]\d|2[0-3]):([0-5]\d)$/, 'Gunakan format jam HH:mm'),
    end_time: z.string().regex(/^([01]\d|2[0-3]):([0-5]\d)$/, 'Gunakan format jam HH:mm'),
    notes: z.string().max(500, 'Catatan maksimal 500 karakter').optional()
}).refine((data) => data.end_time > data.start_time, {
    message: 'Jam selesai harus setelah jam mulai',
    path: ['end_time']
}).refine((data) => {
    const bookingDate = new Date(`${data.date}T00:00:00`);
    const day = bookingDate.getDay();
    return day !== 0 && day !== 6;
}, {
    message: 'Booking hanya dapat dilakukan Senin-Jumat',
    path: ['date']
}).refine((data) => data.start_time >= '08:00' && data.end_time <= '17:00', {
    message: 'Booking hanya dapat dilakukan pukul 08:00-17:00',
    path: ['start_time']
});

export type BookingFormValues = z.infer<typeof bookingSchema>;
