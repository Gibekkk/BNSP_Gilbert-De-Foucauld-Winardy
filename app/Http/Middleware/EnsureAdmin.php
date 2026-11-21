<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Only allow requests from authenticated admin users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        abort_unless($user !== null && $user->is_admin === true, 403, 'Akses khusus admin.');

        return $next($request);
    }
}
