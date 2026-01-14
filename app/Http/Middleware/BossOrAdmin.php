<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BossOrAdmin
{
    /**
     * Allow admins or anyone whose funkcija contains "šef"/"sef" or "direktor" (case-insensitive).
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

        $funkcija = mb_strtolower((string)($user->funkcija ?? ''), 'UTF-8');
        if ($funkcija !== '' && (
            str_contains($funkcija, 'šef') ||
            str_contains($funkcija, 'sef') ||
            str_contains($funkcija, 'direktor')
        )) {
            return $next($request);
        }

        abort(403, 'Nemate prava pristupa ovoj stranici.');
    }
}
