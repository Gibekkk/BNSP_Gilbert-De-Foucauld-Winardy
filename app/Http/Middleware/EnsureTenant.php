<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    /**
     * Allow requests only for tenant accounts (not admin) with a customer profile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        abort_unless($user !== null && ! $user->is_admin, 403, 'Khusus akun pelanggan.');
        abort_if($user->customer === null, 403, 'Akun belum terhubung ke profil pelanggan.');

        return $next($request);
    }
}
