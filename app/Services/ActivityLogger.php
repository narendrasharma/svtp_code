<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Centralized activity / audit logger (Phase 11.5D).
 *
 * Append-only: rows are never updated or deleted by application code.
 * Sensitive values are masked centrally so no controller has to
 * remember. Use ActivityLogger::log() from services and controllers;
 * never write ActivityLog rows directly.
 */
class ActivityLogger
{
    /**
     * Keys that must never be stored in clear text.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'new_password', 'token', 'api_key', 'api_secret', 'secret',
        'app_key', 'mail_password', 'bank_account_number', 'account_number',
        'upi_id_secret', 'card_number', 'card_cvv', 'cvv', 'session',
        'cookie', 'remember_token', 'otp', 'private_key',
        'aadhaar', 'aadhar', 'pan_number', 'passport_number',
    ];

    public const MASK = '***masked***';

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $event,
        string $module = 'system',
        ?string $description = null,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
    ): ActivityLog {
        $resolvedActor = $actor ?? Auth::user();
        $impersonatorId = null;

        if ($resolvedActor && session()->has('impersonator_id')) {
            $impersonatorId = (int) session('impersonator_id');
        }

        return ActivityLog::create([
            'actor_user_id' => $resolvedActor?->id,
            'impersonator_user_id' => $impersonatorId,
            'event' => mb_substr($event, 0, 80),
            'module' => mb_substr($module, 0, 40),
            'subject_type' => $subject ? mb_substr($subject::class, 0, 120) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'old_values' => $old !== null ? $this->sanitize($old) : null,
            'new_values' => $new !== null ? $this->sanitize($new) : null,
            'ip_address' => $this->safeIp(),
            'user_agent' => $this->safeUserAgent(),
        ]);
    }

    /**
     * Sanitize arbitrary payloads before persistence.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $lower = strtolower((string) $key);

            if ($this->isSensitiveKey($lower)) {
                $out[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);

                continue;
            }

            if (is_string($value) && mb_strlen($value) > 2000) {
                $out[$key] = mb_substr($value, 0, 2000);

                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    public function isSensitiveKey(string $lowerKey): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($lowerKey === $sensitive || str_contains($lowerKey, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    protected function safeIp(): ?string
    {
        try {
            return mb_substr((string) Request::ip(), 0, 45) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function safeUserAgent(): ?string
    {
        try {
            $ua = (string) Request::header('User-Agent', '');

            return $ua !== '' ? mb_substr($ua, 0, 500) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
