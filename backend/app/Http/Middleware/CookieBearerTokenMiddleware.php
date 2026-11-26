<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CookieBearerTokenMiddleware
{
    /**
     * Audit 496 Parameter SEC (Security):
     * Mengambil token autentikasi dari httpOnly cookie jika header Authorization tidak disertakan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $token = $request->cookie('meetspace_auth_token')
                ?? $request->cookie('auth_token')
                ?? $request->cookie('token');

            if (! $token && ($cookieHeader = $request->header('cookie'))) {
                if (preg_match('/(?:^|;\s*)(?:meetspace_auth_token|auth_token|token)=([^;]+)/', $cookieHeader, $matches)) {
                    $token = urldecode($matches[1]);
                }
            }

            if ($token) {
                try {
                    $token = app('encrypter')->decrypt($token, false);
                } catch (\Throwable) {
                    // Token is already in plain text
                }

                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
