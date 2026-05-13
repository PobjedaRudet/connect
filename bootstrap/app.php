<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\CheckFunkcija;
use App\Http\Middleware\AdminOrFunkcije;
use App\Http\Middleware\BossOrAdmin;
use App\Http\Middleware\AdminOnly;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route middleware aliases
        $middleware->alias([
            'adminOrFunkcije' => AdminOrFunkcije::class,
            'bossOrAdmin' => BossOrAdmin::class,
            'adminOnly' => AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            // For Inertia visits, show a popup on the current page instead of redirecting to a dashboard
            // or rendering a full 403 error page.
            if (!$request->header('X-Inertia')) {
                return null;
            }

            $status = null;
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }

            if ($status !== 403) {
                return null;
            }

            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = 'Nemate pravo pristupa ovoj stranici.';
            }

            $previous = url()->previous();
            $current = $request->fullUrl();

            if (!$previous || $previous === $current) {
                return redirect('/')->with('error', $message);
            }

            return redirect()->back()->with('error', $message);
        });
    })->create();
