import { afterAll, afterEach, beforeAll } from 'vitest';
import { server } from './server';

// Mulai MSW interception sebelum seluruh test dijalankan
beforeAll(() => {
    server.listen({ onUnhandledRequest: 'bypass' });
});

// Reset handlers setelah setiap test untuk mencegah kebocoran state mock
afterEach(() => {
    server.resetHandlers();
});

// Tutup MSW setelah selesai
afterAll(() => {
    server.close();
});
