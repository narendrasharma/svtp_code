<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\StaffPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Staff role & permission management (Phase 11.5A). The protected
 * super-admin role can never be deleted or stripped of permissions.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_protected' => (bool) $role->is_protected,
                'permissions' => $role->permissions()->pluck('name')->all(),
                'users_count' => $role->users_count,
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'allPermissions' => StaffPermissions::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(StaffPermissions::all())],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'description' => $validated['description'] ?? null,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        app(ActivityLogger::class)->log('role.created', 'users', 'Role created: '.$role->name, null, null, ['name' => $role->name, 'permissions' => $validated['permissions'] ?? []]);

        return redirect()->route('admin.roles.index')->with('flash', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_protected' => (bool) $role->is_protected,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ],
            'allPermissions' => StaffPermissions::grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(StaffPermissions::all())],
        ]);

        if ($this->isProtected($role) && $validated['name'] !== $role->name) {
            abort(403, 'The protected Super Admin role cannot be renamed.');
        }

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Protected role always keeps the full catalogue.
        if ($this->isProtected($role)) {
            $role->syncPermissions(StaffPermissions::all());
        } else {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        app(ActivityLogger::class)->log('role.permissions_changed', 'users', 'Role updated: '.$role->name, null, null, ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()]);

        return redirect()->route('admin.roles.index')->with('flash', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            abort(403, 'The protected Super Admin role cannot be deleted.');
        }

        $role->users()->detach();
        $roleName = $role->name;
        $role->delete();

        app(ActivityLogger::class)->log('role.deleted', 'users', 'Role deleted: '.$roleName);

        return redirect()->route('admin.roles.index')->with('flash', 'Role deleted.');
    }

    protected function isProtected(Role $role): bool
    {
        return (bool) $role->is_protected || $role->name === StaffPermissions::SUPER_ADMIN_ROLE;
    }
}
