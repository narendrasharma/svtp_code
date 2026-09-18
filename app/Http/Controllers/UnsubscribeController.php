<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketing unsubscribe behind a signed URL. No login required; only
 * marketing flags change — transactional and security mail is never
 * affected.
 */
class UnsubscribeController extends Controller
{
    public function show(User $user): Response
    {
        return Inertia::render('Unsubscribe', [
            'email' => $user->email,
            'marketing_email_opt_in' => (bool) $user->marketing_email_opt_in,
        ]);
    }

    public function store(User $user): RedirectResponse
    {
        $user->update([
            'marketing_email_opt_in' => false,
            'marketing_sms_opt_in' => false,
            'marketing_whatsapp_opt_in' => false,
        ]);

        return back()->with('flash', 'You have been unsubscribed from marketing messages. Booking and account emails are unaffected.');
    }
}
