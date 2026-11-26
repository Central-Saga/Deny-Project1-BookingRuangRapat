import { describe, expect, it } from 'vitest';
import fs from 'fs';
import path from 'path';
import { ApiError, extractFieldErrors } from '../api/client';

/**
 * Audit 496 - Parameter ROB (Issue #171):
 * Form belum menampilkan error server dan status kirim.
 *
 * Bukti audit:
 * - uji 422 -> pesan error per field tampil: false
 * Ambang batas:
 * - tampil di field (error server per field ditampilkan langsung di input terkait)
 * - status kirim (loading state, aria-busy, button & input disabled, accessible live feedback)
 */
describe('Form Server Error 422 Per-Field & Submission Status Audit (#171)', () => {
    const srcDir = path.resolve(__dirname, '..');
    const componentsDir = path.join(srcDir, 'components');

    describe('Ekstraksi dan Propagasi Error Server 422 (ApiError & extractFieldErrors)', () => {
        it('mengekstraksi error 422 format array objek [{ field, message }] ke dictionary per-field', () => {
            const raw = [
                { field: 'title', message: 'Nama agenda minimal 3 karakter.' },
                { field: 'room_id', message: 'Ruangan yang dipilih tidak valid.' },
                { field: 'participant_count', message: 'Jumlah peserta melebihi kapasitas ruangan.' },
            ];
            const result = extractFieldErrors(raw);

            expect(result.title).toBe('Nama agenda minimal 3 karakter.');
            expect(result.room_id).toBe('Ruangan yang dipilih tidak valid.');
            expect(result.participant_count).toBe('Jumlah peserta melebihi kapasitas ruangan.');
        });

        it('mengekstraksi error 422 format Laravel/standard validation dictionary { field: [messages] }', () => {
            const raw = {
                title: ['Nama agenda wajib diisi.'],
                start_time: ['Waktu mulai harus berada di jam operasional kantor.'],
                email: ['Format email tidak valid.'],
            };
            const result = extractFieldErrors(raw);

            expect(result.title).toBe('Nama agenda wajib diisi.');
            expect(result.start_time).toBe('Waktu mulai harus berada di jam operasional kantor.');
            expect(result.email).toBe('Format email tidak valid.');
        });

        it('menangani normalisasi alias field backend ke form field frontend (room -> room_id, participants -> participant_count)', () => {
            const raw = {
                room: ['Ruangan tidak tersedia pada jam tersebut.'],
                participants: ['Peserta minimal 1 orang.'],
            };
            const result = extractFieldErrors(raw);

            expect(result.room_id).toBe('Ruangan tidak tersedia pada jam tersebut.');
            expect(result.participant_count).toBe('Peserta minimal 1 orang.');
        });

        it('membuat instance ApiError dengan status 422 dan fieldErrors yang terisi utuh', () => {
            const apiError = new ApiError({
                message: 'Validasi server gagal.',
                status: 422,
                rawErrors: {
                    title: ['Nama agenda wajib diisi.'],
                    room_id: ['Pilih ruangan rapat.'],
                },
            });

            expect(apiError.status).toBe(422);
            expect(apiError.message).toBe('Validasi server gagal.');
            expect(apiError.fieldErrors.title).toBe('Nama agenda wajib diisi.');
            expect(apiError.fieldErrors.room_id).toBe('Pilih ruangan rapat.');
        });
    });

    describe('Pemeriksaan Pesan Error Server Per-Field pada Form Booking (BookingModal & DashboardPage)', () => {
        it('memastikan BookingModal.tsx menampilkan pesan error per-field dengan aria-invalid dan aria-describedby', () => {
            const bookingModalPath = path.join(componentsDir, 'BookingModal.tsx');
            expect(fs.existsSync(bookingModalPath)).toBe(true);

            const content = fs.readFileSync(bookingModalPath, 'utf-8');

            // 1. ApiError diimpor dan ditangani di catch block
            expect(content).toContain('ApiError');
            expect(content).toMatch(/error\s+instanceof\s+ApiError/);
            expect(content).toContain('setErrors(error.fieldErrors)');

            // 2. Setiap input field terikat dengan aria-invalid dan aria-describedby
            expect(content).toContain('aria-invalid={Boolean(errors.room_id || errors.room)}');
            expect(content).toContain('modal-room-error');
            expect(content).toContain('aria-invalid={Boolean(errors.title || errors.name)}');
            expect(content).toContain('modal-title-error');
            expect(content).toContain('aria-invalid={Boolean(errors.participant_count || errors.participants)}');
            expect(content).toContain('aria-invalid={Boolean(errors.date)}');
            expect(content).toContain('aria-invalid={Boolean(errors.start_time)}');
            expect(content).toContain('aria-invalid={Boolean(errors.end_time)}');

            // 3. FieldError memiliki role="alert" dan data-testid untuk pembuktian audit
            expect(content).toContain('role="alert"');
            expect(content).toContain('data-testid={id || (field ? `${field}-error` : undefined)}');
        });

        it('memastikan DashboardPage.tsx menampilkan pesan error per-field untuk form booking, login, dan pembatalan', () => {
            const dashboardPath = path.join(componentsDir, 'DashboardPage.tsx');
            expect(fs.existsSync(dashboardPath)).toBe(true);

            const content = fs.readFileSync(dashboardPath, 'utf-8');

            // 1. Tangani 422 ApiError pada booking form
            expect(content).toMatch(/submitError\s+instanceof\s+ApiError/);
            expect(content).toContain('setFormErrors(submitError.fieldErrors)');

            // 2. Input booking form memiliki aria-invalid dan FieldError per field
            expect(content).toContain('aria-invalid={Boolean(formErrors.room_id)}');
            expect(content).toContain('aria-invalid={Boolean(formErrors.title)}');
            expect(content).toContain('aria-invalid={Boolean(formErrors.participant_count)}');
            expect(content).toContain('aria-invalid={Boolean(formErrors.date)}');
            expect(content).toContain('aria-invalid={Boolean(formErrors.start_time)}');
            expect(content).toContain('aria-invalid={Boolean(formErrors.end_time)}');

            // 3. Login form menampilkan error per-field (email & password)
            expect(content).toContain('aria-invalid={Boolean(loginFieldErrors.email)}');
            expect(content).toContain('aria-invalid={Boolean(loginFieldErrors.password)}');
            expect(content).toContain('id="email-error"');
            expect(content).toContain('id="password-error"');

            // 4. Modal pembatalan menampilkan error alasan per-field
            expect(content).toContain('id="cancellation-reason-error"');
            expect(content).toContain('aria-invalid={Boolean(actionError)}');
        });

        it('memastikan AdminDashboardPage.tsx menampilkan error per-field untuk inventaris ruangan dan akun pengguna', () => {
            const adminPath = path.join(componentsDir, 'AdminDashboardPage.tsx');
            expect(fs.existsSync(adminPath)).toBe(true);

            const content = fs.readFileSync(adminPath, 'utf-8');

            // 1. ApiError ditangani pada saveRoom dan createUserAccount
            expect(content).toMatch(/saveError\s+instanceof\s+ApiError/);
            expect(content).toContain('setRoomErrors(saveError.fieldErrors)');
            expect(content).toMatch(/createError\s+instanceof\s+ApiError/);
            expect(content).toContain('setUserFieldErrors(createError.fieldErrors)');

            // 2. Room form per-field error & a11y attributes
            expect(content).toContain('id="room-name-error"');
            expect(content).toContain('id="room-capacity-error"');
            expect(content).toContain('id="room-location-error"');
            expect(content).toContain('aria-invalid={Boolean(errors.name)}');

            // 3. User form per-field error & a11y attributes
            expect(content).toContain('id="user-name-error"');
            expect(content).toContain('id="user-email-error"');
            expect(content).toContain('id="user-password-error"');
            expect(content).toContain('aria-invalid={Boolean(fieldErrors.email)}');
        });
    });

    describe('Pemeriksaan Status Kirim (Submitting / Loading States)', () => {
        it('memverifikasi BookingModal memiliki atribut aria-busy, button disabled, dan screen reader status saat submitting', () => {
            const bookingModalPath = path.join(componentsDir, 'BookingModal.tsx');
            const content = fs.readFileSync(bookingModalPath, 'utf-8');

            // Form dan button status kirim
            expect(content).toContain('aria-busy={submitting}');
            expect(content).toContain('data-loading={submitting}');
            expect(content).toContain('disabled={submitting}');
            expect(content).toContain('Menyimpan booking...');

            // Inputs disabled during submission to prevent mid-flight changes
            expect(content).toContain('disabled={submitting}');

            // Accessible live region for assistive technologies
            expect(content).toContain('role="status" aria-live="polite"');
            expect(content).toContain('Sedang mengirim data booking ke server...');
        });

        it('memverifikasi DashboardPage dan AdminDashboardPage memiliki visual spinner dan status kirim yang jelas', () => {
            const dashboardPath = path.join(componentsDir, 'DashboardPage.tsx');
            const adminPath = path.join(componentsDir, 'AdminDashboardPage.tsx');

            const dashContent = fs.readFileSync(dashboardPath, 'utf-8');
            const adminContent = fs.readFileSync(adminPath, 'utf-8');

            // Dashboard login & booking submit status
            expect(dashContent).toContain('aria-busy={loggingIn}');
            expect(dashContent).toContain('Memverifikasi...');
            expect(dashContent).toContain('aria-busy={submitting}');
            expect(dashContent).toContain('Menyimpan booking...');

            // Admin saving room & creating user status
            expect(adminContent).toContain('aria-busy={saving}');
            expect(adminContent).toContain('Menyimpan...');
            expect(adminContent).toContain('aria-busy={creating}');
            expect(adminContent).toContain('Membuat akun...');
        });
    });
});
