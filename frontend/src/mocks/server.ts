import { setupServer } from 'msw/node';
import { handlers } from './handlers';

/**
 * MSW Server Instance untuk Pengujian Node.js & Vitest.
 */
export const server = setupServer(...handlers);
