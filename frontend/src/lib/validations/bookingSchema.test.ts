import { describe, expect, it } from 'vitest';
import { bookingSchema } from '@/lib/validations/bookingSchema';
import { bookingSchema as rootBookingSchema } from '../../../lib/validations/bookingSchema';

describe('bookingSchema validation (Param 25, 45, 50)', () => {
    const validBooking = {
        room_id: '1',
        title: 'Weekly Product Sync',
        participant_count: '6',
        date: '2026-11-02', // Senin
        start_time: '09:00',
        end_time: '10:30',
        notes: 'Membahas roadmap Q4',
    };

    it('menerima data booking yang valid pada hari kerja dan jam operasional', () => {
        const result = bookingSchema.safeParse(validBooking);
        expect(result.success).toBe(true);

        const rootResult = rootBookingSchema.safeParse(validBooking);
        expect(rootResult.success).toBe(true);
    });

    it('menolak booking pada akhir pekan (Sabtu/Minggu)', () => {
        const saturdayResult = bookingSchema.safeParse({
            ...validBooking,
            date: '2026-10-31', // Sabtu
        });
        expect(saturdayResult.success).toBe(false);
        if (!saturdayResult.success) {
            expect(saturdayResult.error.issues.some((i) => i.message.includes('Senin-Jumat'))).toBe(true);
        }
    });

    it('menolak booking di luar jam operasional 08:00-17:00 atau jam selesai sebelum jam mulai', () => {
        const outsideHours = bookingSchema.safeParse({
            ...validBooking,
            start_time: '07:30',
            end_time: '09:00',
        });
        expect(outsideHours.success).toBe(false);

        const reversedTime = bookingSchema.safeParse({
            ...validBooking,
            start_time: '14:00',
            end_time: '13:00',
        });
        expect(reversedTime.success).toBe(false);
    });

    it('menolak jika field wajib kosong atau format waktu tidak sesuai', () => {
        const emptyFields = bookingSchema.safeParse({
            room_id: '',
            title: '',
            participant_count: '0',
            date: '',
            start_time: 'invalid',
            end_time: 'invalid',
        });
        expect(emptyFields.success).toBe(false);
        if (!emptyFields.success) {
            expect(emptyFields.error.issues.length).toBeGreaterThanOrEqual(4);
        }
    });
});

