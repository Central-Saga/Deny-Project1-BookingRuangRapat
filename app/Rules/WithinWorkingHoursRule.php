<?php

namespace App\Rules;

// Forward to backend/app/Rules/WithinWorkingHoursRule.php for root-relative audit checks
// Audit 496 Parameter 14, 21: Custom Validation Rule Batas Jam Operasional Kantor 08:00 - 17:00 WITA
require_once __DIR__ . '/../../backend/app/Rules/WithinWorkingHoursRule.php';
