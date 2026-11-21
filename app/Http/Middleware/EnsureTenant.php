<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: hanya penyewa (bukan admin) yang boleh lewat.
 */
class EnsureTenant
{
    /**
     * Hanya izinkan akun penyewa (bukan admin) yang memiliki profil pelanggan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        abort_unless($user !== null && ! $user->is_admin, 403, 'Khusus akun pelanggan.');
        abort_if($user->customer === null, 403, 'Akun belum terhubung ke profil pelanggan.');

        return $next($request);
    }
}
