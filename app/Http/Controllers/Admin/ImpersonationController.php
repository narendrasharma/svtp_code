<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonation
    ) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin->isAdmin(), 403);

        $this->impersonation->start($request, $admin, $user);

        // Redirect to appropriate dashboard based on impersonated role
        if ($user->isVendor()) {
            return redirect()->route('vendor.dashboard')->with('success', "You are now viewing as {$user->name} (Vendor).");
        }

        return redirect()->route('account.dashboard')->with('success', "You are now viewing as {$user->name}.");
    }
}
