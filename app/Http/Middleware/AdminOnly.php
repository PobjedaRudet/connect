<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Allow only users flagged as admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!empty($user->isadmin) || !empty($user->is_admin)) {
            return $next($request);
        }

        abort(403, 'Samo admin ima pristup ovoj stranici.');
    }
}
