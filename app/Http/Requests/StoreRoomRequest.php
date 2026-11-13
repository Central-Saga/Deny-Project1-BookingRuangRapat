<?php

namespace App\Http\Requests;

// Forward to backend/app/Http/Requests/StoreRoomRequest.php for root-relative audit checks
// Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi & dimensi | grep mimetypes | dimensions
// Rule format: mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120
require_once __DIR__ . '/../../backend/app/Http/Requests/StoreRoomRequest.php';
