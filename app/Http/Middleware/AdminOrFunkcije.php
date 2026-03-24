<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrFunkcije
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware(['auth','adminOrFunkcije:Direktor Komercijale,Direktor Proizvodnje'])
     */
    public function handle(Request $request, Closure $next, ...$funkcije): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!empty($user->isadmin)) {
            return $next($request);
        }

        $userFunkcija = trim((string)($user->funkcija ?? ''));
        $allowed = array_map(fn($f) => trim((string)$f), $funkcije);

        if (!empty($allowed) && in_array($userFunkcija, $allowed, true)) {
            return $next($request);
        }

        return redirect('/')->with('error', 'Nemate pravo pristupa ovoj stranici.');
    }
}
