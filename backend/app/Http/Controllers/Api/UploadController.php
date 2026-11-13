<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(FileUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('uploads', 'public');

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'File berhasil diunggah.',
            'data' => [
                'url' => asset('storage/' . $path),
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ],
            'errors' => [],
        ], 201);
    }
}
