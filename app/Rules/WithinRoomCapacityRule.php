<?php

namespace App\Rules;

// Forward to backend/app/Rules/WithinRoomCapacityRule.php for root-relative audit checks
// Audit 496 Parameter 14, 21: Custom Validation Rule Jumlah Peserta Tidak Melebihi Kapasitas Ruangan
require_once __DIR__ . '/../../backend/app/Rules/WithinRoomCapacityRule.php';
