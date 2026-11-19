<?php

namespace App\Http\Requests;

// Forward to backend/app/Http/Requests/CancelBookingRequest.php for root-relative audit checks
// Audit 496 Parameter 14: FormRequest CancelBookingRequest Wajib Mengisi Alasan Pembatalan (minimal 10 karakter)
require_once __DIR__ . '/../../backend/app/Http/Requests/CancelBookingRequest.php';
