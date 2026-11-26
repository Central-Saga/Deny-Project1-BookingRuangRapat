<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Audit 496 Parameter SEC (Issue #176):
     * Memasang header keamanan dasar lengkap:
     * - HSTS: Strict-Transport-Security
     * - nosniff: X-Content-Type-Options
     * - frame: X-Frame-Options
     * - referrer: Referrer-Policy
     * - permissions: Permissions-Policy
     * - csp: Content-Security-Policy (tanpa unsafe-inline)
     * - Menghapus X-Powered-By
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // CSP terpasang tanpa unsafe-inline
        $csp = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' http://localhost:8000 http://127.0.0.1:8000; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
        $response->headers->set('Content-Security-Policy', $csp);

        // Pastikan X-Powered-By dihapus dari response headers
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
