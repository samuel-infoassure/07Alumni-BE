<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'phone'      => $validated['phone'],
            ]);

            $user->profile()->create([]);

            return $user;
        });

        // Issue token
        $plainToken = Str::random(80);
        $user->api_token = hash('sha256', $plainToken);
        $user->save();

        // Assign default member role
        $memberRole = Role::where('name', 'member')->first();
        if ($memberRole) {
            $user->roles()->attach($memberRole->id);
        }

        // Auto-join the general alumni chat group
        $general = ChatGroup::where('type', 'public')->orderBy('id')->first();
        if ($general && ! $general->members()->where('user_id', $user->id)->exists()) {
            $general->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
        }

        $user->load('profile');

        return $this->success([
            'token' => $plainToken,
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'phone'       => $user->phone,
                'email'       => $user->email,
                'roles'       => $user->load('roles')->roleNames(),
                'permissions' => $user->permissionNames(),
            ],
        ], 'Registration successful. Welcome to 07 Engineering Alumni!', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->failure('Invalid credentials.', 401);
        }

        $plainToken = Str::random(80);
        $user->api_token = hash('sha256', $plainToken);
        $user->save();

        $user->load('profile');

        return $this->success([
            'token' => $plainToken,
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'phone'       => $user->phone,
                'email'       => $user->email,
                'roles'       => $user->load('roles.permissions')->roleNames(),
                'permissions' => $user->permissionNames(),
            ],
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->api_token = null;
            $user->save();
        }

        return $this->success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return $this->success([
            'id'          => $user->id,
            'name'        => $user->name,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'roles'       => $user->load('roles.permissions')->roleNames(),
            'permissions' => $user->permissionNames(),
        ], 'User loaded.');
    }
}
