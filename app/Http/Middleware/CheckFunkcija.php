<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFunkcija
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware(['auth','funkcije:Radnik,Šef Komercijale'])
     */
    public function handle(Request $request, Closure $next, ...$funkcije): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $userFunkcija = trim((string)($user->funkcija ?? ''));
        $allowed = array_map(fn($f) => trim((string)$f), $funkcije);

        if (empty($allowed) || in_array($userFunkcija, $allowed, true)) {
            return $next($request);
        }

        abort(403, 'Nemate prava pristupa za ovu rutu.');
    }
}
