import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import DashboardPage from '@/components/DashboardPage';

export const dynamic = 'force-dynamic';

/**
 * Audit 496 Parameter 3.6 / Issue #161:
 * Server-side Auth Guard memastikan /dashboard tidak mengembalikan HTTP 200 tanpa sesi.
 */
export default async function Page() {
    const cookieStore = await cookies();
    const token =
        cookieStore.get('meetspace_auth_token')?.value ||
        cookieStore.get('auth_token')?.value ||
        cookieStore.get('token')?.value ||
        cookieStore.get('session')?.value;

    if (!token) {
        redirect('/');
    }

    return <DashboardPage />;
}
