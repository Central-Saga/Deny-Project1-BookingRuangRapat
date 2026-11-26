import { NotFoundFallback } from '@/shared/ui';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * 404 Not Found Page untuk rute yang tidak ditemukan atau deep link tak valid.
 * Ambang batas: bukan blank (menampilkan UI 404 ramah pengguna).
 */
export default function NotFound() {
    return (
        <NotFoundFallback
            title="Halaman Tidak Ditemukan"
            description="Halaman atau alamat yang Anda tuju tidak tersedia, telah dipindahkan, atau Anda belum memiliki hak akses."
            homeHref="/"
            homeLabel="Kembali ke Beranda"
        />
    );
}
