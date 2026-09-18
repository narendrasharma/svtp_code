<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Account invitations for offline-created customers. Sends a one-time
 * expiring claim link — never a plaintext password.
 */
class InvitationController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 422, 'Only customer accounts can be invited.');

        $this->invitations->invite($user, $request->user());

        return back()->with('flash', "Invitation emailed to {$user->email}. The link expires in 72 hours.");
    }
}
