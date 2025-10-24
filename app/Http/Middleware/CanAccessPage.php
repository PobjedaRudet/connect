<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\Funkcija;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanAccessPage
{
    /**
     * Handle an incoming request.
     * Optional parameter $routeName forces checking that name; otherwise uses current route name.
     * Fail-open: if page mapping doesn't exist, allow access.
     */
    public function handle(Request $request, Closure $next, ?string $routeName = null): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request); // 'auth' middleware should handle
        }
        $name = $routeName ?: optional($request->route())->getName();
        if (!$name) {
            return $next($request); // unnamed routes not enforced
        }

        $page = Page::where('route_name', $name)->first();
        if (!$page) {
            return $next($request); // not configured -> allow
        }

        $funkcijaVal = $user->funkcija ?? null;
        if (!$funkcijaVal) {
            abort(403, 'Pristup odbijen: funkcija nije postavljena.');
        }

        // Normalize to canonical funkcija
        $canonical = Funkcija::where('Funkcija', $funkcijaVal)->value('Funkcija');
        if (!$canonical) {
            // Case-insensitive fallback
            $all = Funkcija::pluck('Funkcija');
            foreach ($all as $f) {
                if (mb_strtolower(trim($f), 'UTF-8') === mb_strtolower(trim($funkcijaVal), 'UTF-8')) {
                    $canonical = $f; break;
                }
            }
        }

        if (!$canonical) {
            abort(403, 'Pristup odbijen: funkcija nije prepoznata.');
        }

        $allowed = $page->funkcije()->where('funkcija', $canonical)->exists();
        if (!$allowed) {
            abort(403, 'Nemate pravo pristupa ovoj stranici.');
        }

        return $next($request);
    }
}
