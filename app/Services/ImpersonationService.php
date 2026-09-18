<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationService
{
    public const SESSION_KEY_ADMIN_ID = 'impersonation.admin_id';

    public const SESSION_KEY_IMPERSONATED_ID = 'impersonation.impersonated_id';

    public const SESSION_KEY_STARTED_AT = 'impersonation.started_at';

    public const SESSION_KEY_LOG_ID = 'impersonation.log_id';

    public const SESSION_KEY_ORIGINAL_USER_ID = 'impersonation.original_user_id';

    public function isActive(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY_ADMIN_ID)
            && $request->session()->has(self::SESSION_KEY_IMPERSONATED_ID);
    }

    public function isActiveGlobal(): bool
    {
        return session()->has(self::SESSION_KEY_ADMIN_ID);
    }

    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY_ADMIN_ID);
    }

    public function canImpersonate(User $admin, User $target): bool
    {
        if (! $admin->isAdmin()) {
            return false;
        }

        // Phase 11.5A: impersonation is a granted staff permission.
        // Legacy full admins (no staff roles) keep it via the
        // hasStaffPermission compatibility path; role-assigned staff need
        // users.impersonate explicitly.
        if (! $admin->hasStaffPermission('users.impersonate')) {
            return false;
        }

        if ($admin->id === $target->id) {
            return false;
        }

        if ($target->isAdmin()) {
            return false;
        }

        // Only allow customer or approved vendor.
        if ($target->isVendor()) {
            // Check vendor profile exists and is active
            $profile = $target->vendorProfile;
            if (! $profile || ! $profile->is_active) {
                return false;
            }

            return true;
        }

        if ($target->isCustomer()) {
            return true;
        }

        return false;
    }

    public function start(Request $request, User $admin, User $target): ImpersonationLog
    {
        if ($this->isActive($request)) {
            abort(422, 'Nested impersonation is not allowed. Return to admin first.');
        }

        if (! $this->canImpersonate($admin, $target)) {
            abort(403, 'Cannot impersonate this user.');
        }

        $log = ImpersonationLog::create([
            'admin_user_id' => $admin->id,
            'impersonated_user_id' => $target->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Store original admin identity securely in session.
        $request->session()->put(self::SESSION_KEY_ADMIN_ID, $admin->id);
        $request->session()->put(self::SESSION_KEY_IMPERSONATED_ID, $target->id);
        $request->session()->put(self::SESSION_KEY_STARTED_AT, now()->toIso8601String());
        $request->session()->put(self::SESSION_KEY_LOG_ID, $log->id);
        $request->session()->put(self::SESSION_KEY_ORIGINAL_USER_ID, $admin->id);

        // Regenerate session to reduce fixation risk, keep impersonation data.
        $request->session()->regenerate();

        // Re-put after regenerate if needed (regenerate keeps data, but ensure)
        $request->session()->put(self::SESSION_KEY_ADMIN_ID, $admin->id);
        $request->session()->put(self::SESSION_KEY_IMPERSONATED_ID, $target->id);
        $request->session()->put(self::SESSION_KEY_LOG_ID, $log->id);

        Auth::loginUsingId($target->id);

        app(ActivityLogger::class)->log('impersonation.started', 'system', "Impersonation started: {$admin->email} as {$target->email}", $log, null, null, $admin);

        return $log;
    }

    public function stop(Request $request): ?User
    {
        if (! $this->isActive($request)) {
            return null;
        }

        $adminId = $request->session()->get(self::SESSION_KEY_ADMIN_ID);
        $logId = $request->session()->get(self::SESSION_KEY_LOG_ID);

        if ($logId) {
            $log = ImpersonationLog::find($logId);
            if ($log && $log->ended_at === null) {
                $log->ended_at = now();
                $log->save();
            }
        }

        $admin = User::find($adminId);

        // Clear impersonation state
        $request->session()->forget([
            self::SESSION_KEY_ADMIN_ID,
            self::SESSION_KEY_IMPERSONATED_ID,
            self::SESSION_KEY_STARTED_AT,
            self::SESSION_KEY_LOG_ID,
            self::SESSION_KEY_ORIGINAL_USER_ID,
        ]);

        $request->session()->regenerate();

        if ($admin) {
            Auth::loginUsingId($admin->id);

            app(ActivityLogger::class)->log('impersonation.ended', 'system', 'Impersonation ended, returned to admin.', $log ?? null, null, null, $admin);

            return $admin;
        }

        Auth::logout();

        return null;
    }

    public function getImpersonatedUser(Request $request): ?User
    {
        if (! $this->isActive($request)) {
            return null;
        }

        return User::find($request->session()->get(self::SESSION_KEY_IMPERSONATED_ID));
    }

    public function getAdminUser(Request $request): ?User
    {
        if (! $this->isActive($request)) {
            return null;
        }

        return User::find($request->session()->get(self::SESSION_KEY_ADMIN_ID));
    }

    public function getBannerData(Request $request): ?array
    {
        if (! $this->isActive($request)) {
            return null;
        }

        $impersonated = $this->getImpersonatedUser($request);
        $admin = $this->getAdminUser($request);

        if (! $impersonated) {
            return null;
        }

        return [
            'admin_name' => $admin?->name ?? 'Admin',
            'impersonated_name' => $impersonated->name,
            'impersonated_role' => $impersonated->role,
            'started_at' => $request->session()->get(self::SESSION_KEY_STARTED_AT),
        ];
    }
}
