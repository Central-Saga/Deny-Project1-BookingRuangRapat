import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';

export default async function RuanganLayout({
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
