/**
 * Frontend Data Types disinkronisasikan langsung dari kontrak OpenAPI 3.1.0 (Single Source of Truth).
 * Mencegah duplikasi tipe manual antara backend dan frontend (Audit 496 - Issue #168).
 */

import type { components } from './openapi';

export type User = components['schemas']['User'];
export type Room = components['schemas']['Room'];
export type Booking = components['schemas']['Booking'];
export type RoomInput = components['schemas']['RoomInput'];
export type BookingInput = components['schemas']['BookingInput'];
export type UserCreateInput = components['schemas']['UserCreateInput'];
export type UserUpdateInput = components['schemas']['UserUpdateInput'];
export type ErrorItem = components['schemas']['ErrorItem'];
export type ErrorEnvelope = components['schemas']['ErrorEnvelope'];
export type PaginationMeta = components['schemas']['PaginationMeta'];

export type { components, paths } from './openapi';
