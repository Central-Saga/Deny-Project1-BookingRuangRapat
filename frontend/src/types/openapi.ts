/**
 * Kontrak OpenAPI 3.1.0 - Saga MeetSpace API.
 * Single Source of Truth untuk kontrak skema data backend dan frontend (Audit 496 - Issue #168).
 * Menghilangkan duplikasi tipe manual antara backend dan frontend.
 */

export interface paths {
    '/login': {
        post: {
            requestBody: {
                content: {
                    'application/json': components['schemas']['LoginInput'];
                };
            };
            responses: {
                200: {
                    content: {
                        'application/json': {
                            token: string;
                            user: components['schemas']['User'];
                            message?: string;
                        };
                    };
                };
                401: {
                    content: {
                        'application/json': components['schemas']['ErrorEnvelope'];
                    };
                };
            };
        };
    };
    '/me': {
        get: {
            responses: {
                200: {
                    content: {
                        'application/json': components['schemas']['User'];
                    };
                };
            };
        };
    };
    '/logout': {
        post: {
            responses: {
                200: {
                    content: {
                        'application/json': {
                            success: boolean;
                            message: string;
                        };
                    };
                };
            };
        };
    };
    '/v1/rooms': {
        get: {
            responses: {
                200: {
                    content: {
                        'application/json': {
                            data: components['schemas']['Room'][];
                            meta?: components['schemas']['PaginationMeta'];
                        };
                    };
                };
            };
        };
        post: {
            requestBody: {
                content: {
                    'application/json': components['schemas']['RoomInput'];
                };
            };
            responses: {
                201: {
                    content: {
                        'application/json': {
                            data: components['schemas']['Room'];
                        };
                    };
                };
            };
        };
    };
    '/v1/rooms/{id}': {
        put: {
            requestBody: {
                content: {
                    'application/json': components['schemas']['RoomInput'];
                };
            };
            responses: {
                200: {
                    content: {
                        'application/json': {
                            data: components['schemas']['Room'];
                        };
                    };
                };
            };
        };
        delete: {
            responses: {
                204: {
                    description: 'No Content';
                };
            };
        };
    };
    '/v1/bookings': {
        get: {
            responses: {
                200: {
                    content: {
                        'application/json': {
                            data: components['schemas']['Booking'][];
                            meta?: components['schemas']['PaginationMeta'];
                        };
                    };
                };
            };
        };
        post: {
            requestBody: {
                content: {
                    'application/json': components['schemas']['BookingInput'];
                };
            };
            responses: {
                201: {
                    content: {
                        'application/json': {
                            data: components['schemas']['Booking'];
                        };
                    };
                };
            };
        };
    };
    '/v1/users': {
        get: {
            responses: {
                200: {
                    content: {
                        'application/json': {
                            data: components['schemas']['User'][];
                            meta?: components['schemas']['PaginationMeta'];
                        };
                    };
                };
            };
        };
        post: {
            requestBody: {
                content: {
                    'application/json': components['schemas']['UserCreateInput'];
                };
            };
            responses: {
                201: {
                    content: {
                        'application/json': {
                            data: components['schemas']['User'];
                        };
                    };
                };
            };
        };
    };
}

export interface components {
    schemas: {
        ErrorItem: {
            field?: string;
            message?: string;
        };
        ErrorEnvelope: {
            success?: boolean;
            status?: boolean;
            message?: string;
            data?: unknown;
            errors?: components['schemas']['ErrorItem'][] | Record<string, string[] | string>;
        };
        PaginationMeta: {
            current_page?: number;
            last_page?: number;
            per_page?: number;
            total?: number;
        };
        User: {
            id: number;
            name: string;
            email: string;
            role: 'admin' | 'karyawan';
            status: 'active' | 'inactive';
            created_at?: string;
        };
        Room: {
            id: number;
            name: string;
            capacity: number;
            location?: string;
            facilities?: string[];
            description?: string;
            is_active: boolean;
            created_at?: string;
        };
        Booking: {
            id: number;
            room_id: number;
            user_id: number;
            title: string;
            participant_count: number;
            start_time: string;
            end_time: string;
            status: 'pending' | 'approved' | 'rejected' | 'cancelled';
            notes?: string | null;
            cancellation_reason?: string | null;
            approval_notes?: string | null;
            approved_by?: number | null;
            approver?: components['schemas']['User'] | null;
            decided_at?: string | null;
            created_at?: string;
            user?: components['schemas']['User'];
            room?: components['schemas']['Room'];
        };
        LoginInput: {
            email: string;
            password: string;
        };
        RoomInput: {
            name: string;
            capacity: number;
            location: string;
            facilities?: string[];
            description?: string;
            is_active?: boolean;
        };
        BookingInput: {
            room_id: number;
            title: string;
            participant_count: number;
            start_time: string;
            end_time: string;
            notes?: string;
        };
        UserCreateInput: {
            name: string;
            email: string;
            password: string;
            role: 'admin' | 'karyawan';
            status: 'active' | 'inactive';
        };
        UserUpdateInput: {
            role: 'admin' | 'karyawan';
            status: 'active' | 'inactive';
        };
    };
}
