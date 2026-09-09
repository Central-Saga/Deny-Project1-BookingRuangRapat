const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

export async function getRooms() {
    const res = await fetch(`${API_URL}/v1/rooms`, { cache: 'no-store' });
    if (!res.ok) throw new Error('Gagal memuat data ruangan');
    const json = await res.json();
    return json.data || json; // Mendukung format Laravel Resource Collection
}

export async function getBookings() {
    const res = await fetch(`${API_URL}/v1/bookings`, { cache: 'no-store' });
    if (!res.ok) throw new Error('Gagal memuat daftar booking');
    const json = await res.json();
    return json.data || json;
}

export async function createBooking(data) {
    const res = await fetch(`${API_URL}/v1/bookings`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    const result = await res.json();
    if (!res.ok) throw new Error(result.message || 'Gagal membuat booking');
    return result;
}

export async function cancelBooking(id) {
    const res = await fetch(`${API_URL}/v1/bookings/${id}/cancel`, {
        method: 'PATCH',
        headers: { 
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
    });
    
    const data = await res.json().catch(() => ({}));
    
    if (!res.ok) {
        throw new Error(data.message || 'Gagal membatalkan booking');
    }
    
    return data;
}