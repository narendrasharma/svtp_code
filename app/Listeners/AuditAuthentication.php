<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Authentication audit trail (11.5D).
 *
 * Records login/logout and failed attempts WITHOUT storing passwords,
 * tokens or any credential material — only who, when and from where.
 */
class AuditAuthentication
{
    public function onLogin(Login $event): void
    {
        app(ActivityLogger::class)->log(
            'auth.login',
            'auth',
            'Login: '.$event->user->email,
            $event->user,
            null,
            null,
            $event->user,
        );
    }

    public function onLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        app(ActivityLogger::class)->log(
            'auth.logout',
            'auth',
            'Logout: '.$event->user->email,
            $event->user,
            null,
            null,
            $event->user,
        );
    }

    public function onFailed(Failed $event): void
    {
        // Never store the attempted password — only the identifier.
        app(ActivityLogger::class)->log(
            'auth.failed_login',
            'auth',
            'Failed login attempt for: '.(is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : 'unknown'),
            null,
            null,
            ['identifier' => is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null],
            null,
        );
    }
}
