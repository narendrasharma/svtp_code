<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\StaffPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Internal team management (Phase 11.5A). Staff accounts are users with
 * role=admin plus staff roles. Customer/vendor creation is NOT exposed
 * here — the platform-wide Users manager keeps that visibility.
 */
class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $staff = User::query()
            ->where('role', UserRole::Admin->value)
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $staff->getCollection()->transform(fn (User $user): array => $this->present($user));

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Staff/Form', [
            'member' => null,
            'roles' => $this->assignableRoles(auth()->user()),
            'allPermissions' => StaffPermissions::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $this->authorizeRoles($request->user(), $validated['roles'] ?? []);

        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Admin->value,
        ]);

        $member->syncRoles($validated['roles'] ?? []);

        app(ActivityLogger::class)->log('staff.created', 'users', 'Staff member created: '.$member->email, $member, null, ['roles' => $validated['roles'] ?? []]);

        return redirect()->route('admin.staff.index')->with('flash', 'Staff member created.');
    }

    public function show(User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);

        return redirect()->route('admin.staff.edit', $staff);
    }

    public function edit(User $staff): Response
    {
        abort_unless($staff->isAdmin(), 404);

        return Inertia::render('Admin/Staff/Form', [
            'member' => $this->present($staff->load('roles:id,name')),
            'roles' => $this->assignableRoles(auth()->user()),
            'allPermissions' => StaffPermissions::grouped(),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $roles = $validated['roles'] ?? $staff->roles()->pluck('name')->all();
        $this->authorizeRoles($request->user(), $roles, $staff);

        $staff->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $staff->password = $validated['password'];
        }

        $staff->save();
        $oldRoles = $staff->roles()->pluck('name')->all();
        $staff->syncRoles($roles);

        app(ActivityLogger::class)->log('staff.roles_changed', 'users', 'Staff roles updated: '.$staff->email, $staff, ['roles' => $oldRoles], ['roles' => $roles]);

        return redirect()->route('admin.staff.index')->with('flash', 'Staff member updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(User $user): array
    {
        $roleNames = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $roleNames,
            'is_super_admin' => $user->isSuperAdmin(),
            'is_legacy_admin' => $roleNames === [],
            'effective_permissions' => $user->staffPermissionNames(),
            'created_at' => $user->created_at,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function assignableRoles(?User $actor): array
    {
        return Role::orderBy('name')
            ->get(['id', 'name', 'description', 'is_protected'])
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'description' => $role->description,
                'is_protected' => (bool) $role->is_protected,
                // Only super admins may hand out the super-admin role —
                // regular staff can never elevate themselves or others.
                'assignable' => $role->name !== StaffPermissions::SUPER_ADMIN_ROLE
                    || ($actor !== null && $actor->isSuperAdmin()),
            ])
            ->all();
    }

    /**
     * Staff cannot self-elevate: granting super-admin requires the actor
     * to already be a super admin, and nobody may strip super-admin from
     * their own account.
     */
    protected function authorizeRoles(?User $actor, array $roles, ?User $target = null): void
    {
        if (in_array(StaffPermissions::SUPER_ADMIN_ROLE, $roles, true)
            && ($actor === null || ! $actor->isSuperAdmin())) {
            abort(403, 'Only a Super Admin can grant the Super Admin role.');
        }

        if ($target !== null && $actor !== null && $target->id === $actor->id
            && $target->isSuperAdmin()
            && ! in_array(StaffPermissions::SUPER_ADMIN_ROLE, $roles, true)) {
            abort(403, 'You cannot remove Super Admin from your own account.');
        }
    }
}
