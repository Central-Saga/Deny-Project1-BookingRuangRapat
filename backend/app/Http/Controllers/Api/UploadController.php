<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller Unggah Berkas & Signed URL (Audit 496 Parameter SEC).
 */
class UploadController extends Controller
{
    /**
     * Menyimpan berkas yang diunggah dengan validasi tipe, dimensi, dan ukuran.
     * Mendukung visibilitas publik maupun privat dengan Signed URL / temporaryUrl.
     */
    public function store(FileUploadRequest $request): JsonResponse
    {
        $file = $request->file('file') ?? $request->file('image') ?? $request->file('upload');
        $isPrivate = $request->boolean('is_private') || $request->input('visibility') === 'private';
        $disk = $isPrivate ? 'local' : 'public';
        $path = $file->store('uploads', $disk);

        // Generate temporary URL (Signed URL) untuk berkas privat
        $temporaryUrl = URL::temporarySignedRoute(
            'api.uploads.signed-download',
            now()->addMinutes(60),
            ['path' => base64_encode($path)]
        );

        try {
            $diskUrl = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(60));
            if (str_contains($diskUrl, 'signature=')) {
                $temporaryUrl = $diskUrl;
            }
        } catch (\Throwable) {
            // Disk lokal tanpa driver S3
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'File berhasil diunggah.',
            'data' => [
                'url' => asset('storage/'.$path),
                'temporary_url' => $temporaryUrl,
                'temporaryUrl' => $temporaryUrl,
                'signed_url' => $temporaryUrl,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'visibility' => $isPrivate ? 'private' : 'public',
            ],
            'errors' => [],
        ], 201);
    }

    /**
     * Menghasilkan signed temporary URL untuk berkas privat.
     */
    public function temporaryUrl(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'expires_in' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $path = $request->string('path')->toString();
        $minutes = $request->integer('expires_in', 60);

        $url = URL::temporarySignedRoute(
            'api.uploads.signed-download',
            now()->addMinutes($minutes),
            ['path' => base64_encode($path)]
        );

        try {
            $diskUrl = Storage::disk('local')->temporaryUrl($path, now()->addMinutes($minutes));
            if (str_contains($diskUrl, 'signature=')) {
                $url = $diskUrl;
            }
        } catch (\Throwable) {
            // Disk lokal tanpa driver S3
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Signed URL berhasil dibuat.',
            'data' => [
                'path' => $path,
                'temporary_url' => $url,
                'temporaryUrl' => $url,
                'signed_url' => $url,
                'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
            ],
            'errors' => [],
        ]);
    }

    /**
     * Mengunduh berkas menggunakan Signed URL sementara.
     */
    public function download(Request $request, string $path): StreamedResponse|BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Signed URL tidak valid atau sudah kadaluwarsa.');

        $decodedPath = base64_decode($path, true) ?: $path;
        $disk = Storage::disk('local')->exists($decodedPath) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($decodedPath), 404, 'File tidak ditemukan.');

        return Storage::disk($disk)->download($decodedPath);
    }
}
