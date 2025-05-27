<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $redirect = $request->input('redirect', 'dashboard');

        /* $user = Auth::user();
        if ($user->current_team_id == 1) {
            return redirect('/ppz/dashboard');
        }
        if ($user->current_team_id == 2) {
            return redirect('/prodaja/dashboard');
        } */
        Log:
        info($request->all());
        Log::info($redirect);



        if ($redirect === 'ppz i znr') {
            return redirect('/ppz/dashboard');
        }


        if ($redirect === 'sales') {
            return redirect('/prodaja/dashboard');
        }


        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
