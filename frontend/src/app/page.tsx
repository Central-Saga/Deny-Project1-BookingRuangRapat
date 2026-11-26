import dynamic from 'next/dynamic';

const DashboardPage = dynamic(() => import('@/components/DashboardPage'), {
    loading: () => (
        <div className="min-h-screen bg-[#07130f] text-white flex items-center justify-center">
            <div className="flex flex-col items-center gap-3">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" />
                <p className="text-sm text-emerald-200">Memuat MeetSpace...</p>
            </div>
        </div>
    ),
});

export default function Page() {
    return <DashboardPage />;
}
