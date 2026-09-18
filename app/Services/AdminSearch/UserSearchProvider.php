<?php

namespace App\Services\AdminSearch;

use App\Models\User;

/**
 * Platform user search (name/email/phone). Returns identity + role
 * descriptors only — never password hashes, tokens or preferences.
 */
class UserSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('users.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return User::query()
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $found): array => [
                'type' => 'user',
                'label' => $found->name,
                'subtitle' => trim($found->email.' · '.ucfirst((string) $found->role)),
                'url' => route('admin.users.show', $found, absolute: false),
                'icon' => 'bi-person',
            ])
            ->all();
    }
}
