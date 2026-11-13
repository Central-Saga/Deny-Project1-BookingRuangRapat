<?php

namespace App\Http\Requests;

// Forward to backend/app/Http/Requests/FileUploadRequest.php
// Audit 496 Tahap 11 (Param 11.8): Validasi isi file | grep mimetypes -> harus: ada
// Rule: mimetypes:image/jpeg,image/png,image/webp
require_once __DIR__ . '/../../backend/app/Http/Requests/FileUploadRequest.php';

