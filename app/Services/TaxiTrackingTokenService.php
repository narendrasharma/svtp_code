<?php

namespace App\Services;

use App\Models\TaxiBooking;
use App\Models\TaxiTrackingToken;
use App\Models\User;
use App\Support\TaxiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Customer tracking token lifecycle (Phase 12A.9).
 *
 * Tokens are explicit (Admin/Vendor generated), revocable, optionally
 * expiring, single-active per booking. Raw tokens never touch storage
 * or logs — only SHA-256 hashes are persisted and compared.
 */
class TaxiTrackingTokenService
{
    public function trackingEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.customer_tracking.enabled');
    }

    public function tokenExpiryHours(): ?int
    {
        $raw = TaxiSettings::get('taxi.customer_tracking.token_expiry_hours');

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return max(1, (int) $raw);
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Generate a fresh token, revoking prior active ones (single-active
     * model). Returns the RAW token once — it is never recoverable.
     *
     * @return array{token: string, url: string, record: TaxiTrackingToken}
     */
    public function generate(TaxiBooking $booking, ?User $actor = null, string $source = 'manual'): array
    {
        return DB::transaction(function () use ($booking, $actor, $source): array {
            TaxiTrackingToken::where('taxi_booking_id', $booking->id)
                ->active()
                ->update(['revoked_at' => now()]);

            $raw = Str::random(48);
            $expiryHours = $this->tokenExpiryHours();

            $record = TaxiTrackingToken::create([
                'taxi_booking_id' => $booking->id,
                'token_hash' => self::hash($raw),
                'expires_at' => $expiryHours !== null ? now()->addHours($expiryHours) : null,
                'source' => mb_substr($source, 0, 20),
                'created_by' => $actor?->id,
            ]);

            $this->audit($booking, 'taxi_tracking.generated', "Customer tracking link generated for {$booking->reference}.", $actor);

            return ['token' => $raw, 'url' => $this->publicUrl($raw), 'record' => $record->fresh()];
        });
    }

    public function revoke(TaxiTrackingToken $token, ?User $actor = null): TaxiTrackingToken
    {
        return DB::transaction(function () use ($token, $actor): TaxiTrackingToken {
            if ($token->revoked_at === null) {
                $token->forceFill(['revoked_at' => now()])->save();
                $this->audit($token->booking()->firstOrFail(), 'taxi_tracking.revoked', "Customer tracking link revoked for {$token->booking()->firstOrFail()->reference}.", $actor);
            }

            return $token->fresh();
        });
    }

    /**
     * Resolve a raw token to a usable record. Returns null for unknown,
     * revoked, expired or feature-disabled tokens. Touches
     * last_accessed_at at most once per 5 minutes (no per-poll writes).
     */
    public function resolve(string $rawToken): ?TaxiTrackingToken
    {
        if (! $this->trackingEnabled()) {
            return null;
        }

        $record = TaxiTrackingToken::active()
            ->where('token_hash', self::hash($rawToken))
            ->first();

        if ($record === null || ! $record->isUsable()) {
            return null;
        }

        if ($record->last_accessed_at === null || $record->last_accessed_at->lt(now()->subMinutes(5))) {
            $record->forceFill(['last_accessed_at' => now()])->save();
            $record->refresh();
        }

        return $record;
    }

    public function activeForBooking(TaxiBooking $booking): ?TaxiTrackingToken
    {
        return TaxiTrackingToken::active()
            ->where('taxi_booking_id', $booking->id)
            ->latest('id')
            ->first();
    }

    public function publicUrl(string $rawToken): string
    {
        return rtrim((string) config('app.url'), '/').'/taxi/track/'.$rawToken;
    }

    protected function audit(TaxiBooking $booking, string $event, string $description, ?User $actor): void
    {
        try {
            app(ActivityLogger::class)->log($event, 'taxi', $description, $booking, null, null, $actor);
        } catch (\Throwable) {
            // Audit must never break tracking writes.
        }
    }
}
