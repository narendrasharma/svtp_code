<?php

namespace App\Models;

// Illuminate\Foundation\Auth\User as Authenticatable
use App\Enums\UserRole;
use App\Support\StaffPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Spatie RBAC for internal staff. Class-level hasRole() keeps its
     * historic business-account meaning (admin/customer/vendor from the
     * users.role column); the spatie check stays explicit via
     * hasSpatieRole() so the two systems never confuse each other.
     */
    use HasRoles {
        HasRoles::hasRole as hasSpatieRole;
    }

    protected $fillable = [
        'name', 'email', 'password', 'role', 'source', 'phone', 'notification_preferences',
        'marketing_email_opt_in', 'marketing_sms_opt_in', 'marketing_whatsapp_opt_in',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'marketing_email_opt_in' => 'boolean',
            'marketing_sms_opt_in' => 'boolean',
            'marketing_whatsapp_opt_in' => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function accountInvitations()
    {
        return $this->hasMany(AccountInvitation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin->value;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer->value;
    }

    public function isVendor(): bool
    {
        return $this->role === UserRole::Vendor->value;
    }

    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function vendorApplications()
    {
        return $this->hasMany(VendorApplication::class);
    }

    public function vendorVerifications()
    {
        return $this->hasMany(VendorVerification::class);
    }

    /**
     * Business account check AND spatie role check in one compatible
     * method. Plain account values (admin/customer/vendor, optionally as
     * UserRole enum) compare against the users.role column; anything
     * else (role names, arrays, Role models/collections used by spatie
     * internals such as hasPermissionViaRole) delegates to spatie.
     *
     * @param  string|array|Role|Collection|UserRole  $roles
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($roles instanceof UserRole) {
            return $this->role === $roles->value;
        }

        if (is_string($roles) && in_array($roles, array_column(UserRole::cases(), 'value'), true)) {
            if ($this->role === $roles) {
                return true;
            }
        }

        return $this->hasSpatieRole($roles, $guard);
    }

    /**
     * Whether this account is a protected Super Admin. Super Admins pass
     * every staff permission check (see Gate::before in
     * AppServiceProvider) and are the only ones who may manage roles,
     * modules and critical system settings.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasSpatieRole(StaffPermissions::SUPER_ADMIN_ROLE);
    }

    /**
     * Whether this account holds any staff (spatie) roles. Customers and
     * vendors never do; legacy admins pre-dating RBAC may not either.
     */
    public function hasStaffRoles(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->isNotEmpty();
        }

        return $this->roles()->exists();
    }

    /**
     * Central staff permission check (Phase 11.5A RBAC).
     *
     * Compatibility strategy: admins from before RBAC (role=admin with
     * zero staff roles assigned) retain full access so no existing admin
     * is ever locked out. The moment any staff role is assigned,
     * enforcement becomes strict and only granted permissions pass.
     * Customer/vendor accounts always fail staff checks.
     */
    public function hasStaffPermission(string $permission): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->hasStaffRoles()) {
            return true;
        }

        try {
            return $this->hasPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Effective staff permission names for nav/search/UI filtering.
     * Legacy full admins and super admins report every known key.
     *
     * @return array<int, string>
     */
    public function staffPermissionNames(): array
    {
        if (! $this->isAdmin()) {
            return [];
        }

        if ($this->isSuperAdmin() || ! $this->hasStaffRoles()) {
            return StaffPermissions::all();
        }

        return $this->getAllPermissions()->pluck('name')->values()->all();
    }

    /**
     * Phase 9 preference foundation: mail opt-out per category
     * (booking|marketplace). Absent keys default to opted-in. Database
     * notifications are always stored; only mail honors this. No settings
     * UI ships yet — structure is ready for it.
     */
    public function mailPreference(string $category): bool
    {
        $prefs = $this->notification_preferences ?? [];

        return ($prefs[$category] ?? true) !== false;
    }
}
