import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';

/**
 * Audit 496 Frontend Parameter 3.6: Guard auth di server
 * Memastikan rute terproteksi /admin melakukan redirect server-side
 * jika diakses tanpa sesi atau token autentikasi.
 */
export default async function AdminLayout({
    children,
}: {
    children: ReactNode;
}) {
    const cookieStore = await cookies();
    const token =
        cookieStore.get('meetspace_auth_token')?.value ||
        cookieStore.get('auth_token')?.value ||
        cookieStore.get('token')?.value ||
        cookieStore.get('session')?.value;

    if (!token) {
        redirect('/');
    }

    return <>{children}</>;
}
