import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Audit 496 Frontend Parameter 3.6: Guard auth di server
 * Memastikan rute terproteksi (/dashboard, /admin, dll.) mengembalikan server-side redirect jika diakses tanpa sesi/token.
 */
const PROTECTED_PREFIXES = ['/dashboard', '/admin', '/ruangan', '/riwayat', '/jadwal'];

export function middleware(request: NextRequest) {
    const { pathname } = request.nextUrl;

    const isProtected = PROTECTED_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
    );

    if (isProtected) {
        const token =
            request.cookies.get('meetspace_auth_token')?.value ||
            request.cookies.get('auth_token')?.value ||
            request.cookies.get('token')?.value ||
            request.cookies.get('session')?.value ||
            request.headers.get('authorization');

        if (!token) {
            const loginUrl = new URL('/', request.url);
            return NextResponse.redirect(loginUrl);
        }
    }

    return NextResponse.next();
}

export const config = {
    matcher: [
        '/dashboard/:path*',
        '/admin/:path*',
        '/ruangan/:path*',
        '/riwayat/:path*',
        '/jadwal/:path*',
    ],
};

