<?php

namespace App\Http\Controllers;

use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonation
    ) {}

    public function destroy(Request $request): RedirectResponse
    {
        $admin = $this->impersonation->stop($request);

        if ($admin) {
            return redirect()->route('admin.dashboard')->with('success', 'Returned to admin session.');
        }

        return redirect('/')->with('success', 'Impersonation ended.');
    }
}
