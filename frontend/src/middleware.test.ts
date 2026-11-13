import { describe, expect, it } from 'vitest';
import { NextRequest } from 'next/server';
import { middleware } from './middleware';

describe('Server-side Auth Guard Middleware (Frontend param 3.6)', () => {
    it('redirects unauthenticated request to /dashboard to login page (/)', () => {
        const req = new NextRequest('http://localhost:3000/dashboard');
        const res = middleware(req);

        // Harus redirect server-side (status 307 atau 302)
        expect(res.status).toBe(307);
        expect(res.headers.get('location')).toBe('http://localhost:3000/');
    });

    it('redirects unauthenticated request to /admin to login page (/)', () => {
        const req = new NextRequest('http://localhost:3000/admin');
        const res = middleware(req);

        expect(res.status).toBe(307);
        expect(res.headers.get('location')).toBe('http://localhost:3000/');
    });

    it('allows public route / without token', () => {
        const req = new NextRequest('http://localhost:3000/');
        const res = middleware(req);

        // Status 200 / next()
        expect(res.status).toBe(200);
    });

    it('allows protected route /dashboard when auth cookie is present', () => {
        const req = new NextRequest('http://localhost:3000/dashboard', {
            headers: {
                cookie: 'meetspace_auth_token=valid-jwt-token',
            },
        });
        const res = middleware(req);

        expect(res.status).toBe(200);
    });

    it('allows protected route /admin when auth_token cookie is present', () => {
        const req = new NextRequest('http://localhost:3000/admin', {
            headers: {
                cookie: 'auth_token=admin-token',
            },
        });
        const res = middleware(req);

        expect(res.status).toBe(200);
    });
});

