<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ImpersonationService;
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
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            $route = match (true) {
                $user->isAdmin() => 'admin.dashboard',
                $user->isVendor() => 'vendor.dashboard',
                default => 'account.dashboard',
            };

            return redirect()->route($route);
        }

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

        $user = $request->user();
        $dashboard = match (true) {
            $user->isAdmin() => 'admin.dashboard',
            $user->isVendor() => 'vendor.dashboard',
            default => 'account.dashboard',
        };

        return redirect()->intended(route($dashboard, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // If impersonating, stop impersonation instead of logging out admin.
        if (session()->has(ImpersonationService::SESSION_KEY_ADMIN_ID)) {
            app(ImpersonationService::class)->stop($request);

            return redirect()->route('admin.dashboard')->with('success', 'Returned to admin session.');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
