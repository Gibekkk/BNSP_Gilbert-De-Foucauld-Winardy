<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: hanya admin yang boleh lewat.
 */
class EnsureAdmin
{
    /**
     * Hanya izinkan permintaan dari admin yang sudah login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        abort_unless($user !== null && $user->is_admin === true, 403, 'Akses khusus admin.');

        return $next($request);
    }
}
