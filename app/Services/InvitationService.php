<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Notifications\InvitationMail;
use App\Services\Comms\ShareService;
use App\Services\Comms\TemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Secure account claiming for offline-created customers. The invitation
 * carries a one-time expiring token — never a password. Accepting sets
 * the password, verifies the account and logs the customer in without
 * ever creating a duplicate user row.
 */
class InvitationService
{
    public const EXPIRY_HOURS = 72;

    public function __construct(
        protected TemplateService $templates,
        protected ShareService $share,
    ) {}

    /**
     * @return array{invitation: AccountInvitation, token: string}
     */
    public function invite(User $customer, ?User $actor = null): array
    {
        if (! $customer->isCustomer()) {
            throw ValidationException::withMessages(['user' => 'Only customer accounts can be invited.']);
        }

        return DB::transaction(function () use ($customer, $actor): array {
            // Supersede older unused invitations so only one link lives.
            $customer->accountInvitations()->whereNull('used_at')->delete();

            $token = Str::random(40);

            $invitation = AccountInvitation::create([
                'user_id' => $customer->id,
                'token_hash' => hash('sha256', $token),
                'email' => (string) $customer->email,
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
                'created_by' => $actor?->id,
            ]);

            $secureUrl = route('invitation.accept', ['token' => $token]);

            $template = CommunicationTemplate::where('key', 'account_invitation')->first();

            $subject = $this->templates->renderText($template?->email_subject ?? 'Claim your account', [
                'site_name' => config('app.name'),
            ]) ?? 'Claim your account';

            $body = $this->templates->renderText($template?->email_body ?? 'Claim your account here: {{secure_url}}', [
                'customer_name' => $customer->name,
                'secure_url' => $secureUrl,
                'site_name' => config('app.name'),
            ]);

            $customer->notify(new InvitationMail($subject, (string) $body, $secureUrl));

            CommunicationLog::create([
                'recipient_user_id' => $customer->id,
                'recipient_type' => 'customer',
                'channel' => 'email',
                'template_key' => 'account_invitation',
                'event' => 'account_invitation',
                'related_type' => AccountInvitation::class,
                'related_id' => $invitation->id,
                'destination_masked' => CommunicationLog::maskEmail($customer->email),
                'status' => 'sent',
                'provider' => 'mail',
                'sent_at' => now(),
                'created_by' => $actor?->id,
            ]);

            return ['invitation' => $invitation, 'token' => $token];
        });
    }

    public function accept(string $token, string $password): User
    {
        $invitation = AccountInvitation::where('token_hash', hash('sha256', $token))->first();

        if (! $invitation || ! $invitation->isUsable()) {
            throw ValidationException::withMessages(['token' => 'This invitation link is invalid or has expired. Ask our team for a new one.']);
        }

        return DB::transaction(function () use ($invitation, $password): User {
            $user = $invitation->user;

            $user->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $invitation->update(['used_at' => now()]);

            Auth::login($user, true);

            app(ActivityLogger::class)->log('invitation.claimed', 'users', 'Account invitation claimed: '.$user->email, $invitation, null, null, $user);

            return $user->refresh();
        });
    }
}
