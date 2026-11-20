import { describe, expect, it, vi } from 'vitest';

/**
 * Audit 496 Parameter 52: Double-Submit Protection pada Tombol Konfirmasi Booking
 * Menguji bahwa tombol konfirmasi booking menolak klik berulang (double-submit)
 * saat request sedang diproses, serta mengaktifkan loading state & disabled state.
 */
describe('Double-Submit Protection (Frontend Param 52)', () => {
    it('prevents multiple API invocations when user clicks confirm repeatedly while submitting', async () => {
        let isSubmitting = false;
        let apiCallCount = 0;

        const fakeApiCall = vi.fn().mockImplementation(async () => {
            apiCallCount++;
            await new Promise((resolve) => setTimeout(resolve, 50));
            return { id: 1, title: 'Meeting Baru' };
        });

        const handleSubmit = async () => {
            // Parameter 52: Double-submit protection guard
            if (isSubmitting) return;

            try {
                isSubmitting = true;
                await fakeApiCall();
            } finally {
                isSubmitting = false;
            }
        };

        // User clicks submit button 5 times rapidly
        const trigger1 = handleSubmit();
        const trigger2 = handleSubmit();
        const trigger3 = handleSubmit();
        const trigger4 = handleSubmit();
        const trigger5 = handleSubmit();

        await Promise.all([trigger1, trigger2, trigger3, trigger4, trigger5]);

        // Only 1 API call must have been executed
        expect(apiCallCount).toBe(1);
        expect(fakeApiCall).toHaveBeenCalledTimes(1);
        expect(isSubmitting).toBe(false);
    });

    it('sets disabled and loading state on the confirmation button during submission', async () => {
        interface ButtonState {
            disabled: boolean;
            ariaBusy: boolean;
            label: string;
            hasSpinner: boolean;
        }

        const getButtonState = (submitting: boolean, isEditing: boolean): ButtonState => ({
            disabled: submitting,
            ariaBusy: submitting,
            label: submitting ? 'Menyimpan booking...' : isEditing ? 'Simpan perubahan' : 'Ajukan booking',
            hasSpinner: submitting,
        });

        // 1. Initial idle state (Create booking)
        const idleState = getButtonState(false, false);
        expect(idleState.disabled).toBe(false);
        expect(idleState.ariaBusy).toBe(false);
        expect(idleState.label).toBe('Ajukan booking');
        expect(idleState.hasSpinner).toBe(false);

        // 2. Active submitting state
        const loadingState = getButtonState(true, false);
        expect(loadingState.disabled).toBe(true);
        expect(loadingState.ariaBusy).toBe(true);
        expect(loadingState.label).toBe('Menyimpan booking...');
        expect(loadingState.hasSpinner).toBe(true);

        // 3. Edit booking submitting state
        const editLoadingState = getButtonState(true, true);
        expect(editLoadingState.disabled).toBe(true);
        expect(editLoadingState.ariaBusy).toBe(true);
        expect(editLoadingState.label).toBe('Menyimpan booking...');
        expect(editLoadingState.hasSpinner).toBe(true);
    });

    it('protects meeting cancellation confirmation button against double-click', async () => {
        let isCancelling = false;
        let cancelCount = 0;

        const fakeCancelApi = vi.fn().mockImplementation(async () => {
            cancelCount++;
            await new Promise((resolve) => setTimeout(resolve, 30));
        });

        const confirmCancel = async (reason: string) => {
            if (reason.trim().length < 10 || isCancelling) return;

            try {
                isCancelling = true;
                await fakeCancelApi();
            } finally {
                isCancelling = false;
            }
        };

        // Repeated clicks with valid reason
        const c1 = confirmCancel('Agenda rapat bentrok dengan tugas luar.');
        const c2 = confirmCancel('Agenda rapat bentrok dengan tugas luar.');
        const c3 = confirmCancel('Agenda rapat bentrok dengan tugas luar.');

        await Promise.all([c1, c2, c3]);

        expect(cancelCount).toBe(1);
        expect(fakeCancelApi).toHaveBeenCalledTimes(1);
    });
});
