<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight customer identity for phone / walk-in / lead flows.
 *
 * A customer is a users row with role=customer. No password is ever set
 * to something the customer knows here — a random inaccessible secret is
 * stored and the account becomes usable later through the normal
 * invitation/password flows (Communication Center phase). No credentials
 * are emailed by this phase.
 */
class CustomerService
{
    public const PLACEHOLDER_DOMAIN = 'noemail.local';

    /**
     * @return Collection<int, User>
     */
    public function searchCandidates(string $query, int $limit = 8): Collection
    {
        $term = '%'.trim($query).'%';

        if (trim($query) === '') {
            return collect();
        }

        return User::query()
            ->where('role', UserRole::Customer->value)
            ->where(fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone']);
    }

    /**
     * @param  array{name: string, phone: string, email?: ?string, country?: ?string, source?: ?string}  $data
     */
    public function createLightweight(array $data, ?User $actor = null): User
    {
        $email = isset($data['email']) && trim((string) $data['email']) !== ''
            ? strtolower(trim((string) $data['email']))
            : null;

        if ($email !== null && User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A customer with this email already exists. Search and link it instead of creating a duplicate.',
            ]);
        }

        return User::create([
            'name' => trim((string) $data['name']),
            'phone' => trim((string) $data['phone']),
            'email' => $email ?? $this->placeholderEmail(),
            'password' => Hash::make(Str::random(32)),
            'role' => UserRole::Customer->value,
            'source' => $data['source'] ?? null,
            // Placeholder identities never receive mail; the database
            // notification row is still stored for the audit trail.
            'notification_preferences' => $email === null ? ['booking' => false, 'marketplace' => false] : null,
        ]);
    }

    public function isPlaceholder(User $user): bool
    {
        return str_ends_with(strtolower((string) $user->email), '@'.self::PLACEHOLDER_DOMAIN);
    }

    protected function placeholderEmail(): string
    {
        do {
            $email = 'walkin-'.Str::lower(Str::random(12)).'@'.self::PLACEHOLDER_DOMAIN;
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
