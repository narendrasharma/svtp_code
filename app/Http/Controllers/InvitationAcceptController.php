<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public invitation claim. Token lookup only — no user ID in the URL,
 * no login required. Consumes the token on success.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function show(string $token): Response
    {
        return Inertia::render('Auth/ClaimAccount', ['token' => $token, 'expired' => false]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->invitations->accept($token, $validated['password']);

        return redirect()->route('account.dashboard')->with('flash', "Welcome, {$user->name}. Your account is ready.");
    }
}
