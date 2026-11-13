import { describe, expect, it, vi, beforeEach } from 'vitest';
import DashboardLayout from './dashboard/layout';
import AdminLayout from './admin/layout';

vi.mock('next/headers', () => ({
    cookies: vi.fn(),
}));

vi.mock('next/navigation', () => ({
    redirect: vi.fn((url: string) => {
        throw new Error(`REDIRECT:${url}`);
    }),
}));

import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';

describe('Server-side Guard Layouts (Frontend Param 3.6)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('redirects to / when accessing /dashboard without auth token', async () => {
        vi.mocked(cookies).mockResolvedValue({
            get: vi.fn().mockReturnValue(undefined),
        } as any);

        await expect(DashboardLayout({ children: 'content' as any })).rejects.toThrow('REDIRECT:/');
        expect(redirect).toHaveBeenCalledWith('/');
    });

    it('allows accessing /dashboard when auth token cookie is present', async () => {
        vi.mocked(cookies).mockResolvedValue({
            get: vi.fn((name: string) => (name === 'meetspace_auth_token' ? { value: 'token-123' } : undefined)),
        } as any);

        const result = await DashboardLayout({ children: 'dashboard-content' as any });
        expect(result).toBeDefined();
        expect(redirect).not.toHaveBeenCalled();
    });

    it('redirects to / when accessing /admin without auth token', async () => {
        vi.mocked(cookies).mockResolvedValue({
            get: vi.fn().mockReturnValue(undefined),
        } as any);

        await expect(AdminLayout({ children: 'content' as any })).rejects.toThrow('REDIRECT:/');
        expect(redirect).toHaveBeenCalledWith('/');
    });

    it('allows accessing /admin when auth_token cookie is present', async () => {
        vi.mocked(cookies).mockResolvedValue({
            get: vi.fn((name: string) => (name === 'auth_token' ? { value: 'admin-token' } : undefined)),
        } as any);

        const result = await AdminLayout({ children: 'admin-content' as any });
        expect(result).toBeDefined();
        expect(redirect).not.toHaveBeenCalled();
    });
});
