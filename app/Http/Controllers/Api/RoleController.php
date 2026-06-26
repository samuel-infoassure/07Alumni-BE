<?php

namespace App\Http\Controllers\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $roles = Role::with('permissions:id,name,display_name,group')
            ->withCount('users')
            ->get();

        return $this->success($roles->toArray(), 'Roles loaded.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string'],
        ]);

        $role = Role::create($validated);

        return $this->success($role->toArray(), 'Role created.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:100'],
            'description'  => ['nullable', 'string'],
        ]);

        $role->update($validated);

        return $this->success($role->toArray(), 'Role updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $role = Role::findOrFail($id);

        if (in_array($role->name, ['super_admin', 'member'], true)) {
            return $this->failure('System roles cannot be deleted.', 403);
        }

        $role->delete();

        return $this->success([], 'Role deleted.');
    }

    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $role = Role::findOrFail($id);

        if ($role->name === 'super_admin') {
            return $this->failure('Super Admin permissions are managed by the system.', 403);
        }

        $validated = $request->validate([
            'permission_ids'   => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions:id,name,display_name,group');

        return $this->success($role->toArray(), 'Permissions updated.');
    }

    public function permissions(): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return $this->success($permissions->groupBy('group')->toArray(), 'Permissions loaded.');
    }

    public function userRoles(int $userId): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $user = User::with('roles:id,name,display_name')->findOrFail($userId);

        return $this->success([
            'user_id' => $user->id,
            'name'    => $user->name,
            'roles'   => $user->roles->toArray(),
        ], 'User roles loaded.');
    }

    public function assignRoles(Request $request, int $userId): JsonResponse
    {
        if ($guard = $this->superAdminOnly()) {
            return $guard;
        }

        $user = User::with('roles')->findOrFail($userId);

        $validated = $request->validate([
            'role_ids'   => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        // Prevent removing the last super_admin from the system
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $userCurrentlyHasSuper = $user->roles->contains('name', 'super_admin');
            $removingSuper = $userCurrentlyHasSuper && ! in_array($superAdminRole->id, $validated['role_ids']);

            if ($removingSuper) {
                $otherSuperAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                    ->where('id', '!=', $userId)
                    ->count();

                if ($otherSuperAdmins === 0) {
                    return $this->failure('Cannot remove the only Super Administrator from this role.', 422);
                }
            }
        }

        $user->roles()->sync($validated['role_ids']);
        $user->load('roles:id,name,display_name');

        return $this->success([
            'user_id' => $user->id,
            'roles'   => $user->roles->toArray(),
        ], 'Roles assigned.');
    }
}
