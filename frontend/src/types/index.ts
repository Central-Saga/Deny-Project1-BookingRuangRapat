export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'karyawan';
    created_at?: string;
}

export interface Room {
    id: number;
    name: string;
    capacity: number;
    location?: string;
    is_active: boolean;
    created_at?: string;
}

export interface Booking {
    id: number;
    room_id: number;
    user_id: number;
    title: string;
    start_time: string;
    end_time: string;
    status: 'pending' | 'approved' | 'rejected';
    notes?: string;
    user?: User;
    room?: Room;
}