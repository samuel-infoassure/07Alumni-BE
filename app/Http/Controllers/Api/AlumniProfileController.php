<?php

namespace App\Http\Controllers\Api;

use App\Models\AlumniProfile;
use App\Models\User;
use Illuminate\Http\Request;

class AlumniProfileController extends ApiController
{
    /** Standard user+profile response shape. */
    private function userShape(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'profile'     => $user->profile,
            'roles'       => $user->relationLoaded('roles') ? $user->roleNames() : [],
            'permissions' => $user->relationLoaded('roles') ? $user->permissionNames() : [],
        ];
    }

    public function index()
    {
        $profiles = User::with(['profile', 'roles'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $user) => $this->userShape($user));

        return $this->success($profiles->toArray(), 'Alumni directory loaded.');
    }

    public function show(int $id)
    {
        $user = User::with(['profile', 'roles.permissions'])->findOrFail($id);

        return $this->success($this->userShape($user), 'Alumni profile loaded.');
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            // User-level fields
            'first_name'            => ['nullable', 'string', 'max:100'],
            'last_name'             => ['nullable', 'string', 'max:100'],
            'phone'                 => ['nullable', 'string', 'max:30'],
            // Personal Information
            'nick_name'             => ['nullable', 'string', 'max:100'],
            'gender'                => ['nullable', 'in:male,female,other'],
            'birthday'              => ['nullable', 'string', 'regex:/^(0[1-9]|[12]\d|3[01])\/(0[1-9]|1[0-2])$/'],
            // Contact Details
            'city'                  => ['nullable', 'string', 'max:100'],
            'state'                 => ['nullable', 'string', 'max:100'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'next_of_kin'           => ['nullable', 'string', 'max:150'],
            'kin_relationship'      => ['nullable', 'in:Spouse,Parent,Sibling,Child,Others'],
            'kin_phone'             => ['nullable', 'string', 'max:30'],
            // Academic Details
            'department'            => ['nullable', 'string', 'max:255'],
            'matric_number'         => ['nullable', 'string', 'max:50'],
            'graduation_year'       => ['nullable', 'integer', 'min:1990', 'max:2050'],
            // Professional Details
            'current_employer'      => ['nullable', 'string', 'max:255'],
            'current_position'      => ['nullable', 'string', 'max:255'],
            // Declaration
            'accepted_constitution' => ['nullable', 'boolean'],
            'committed_dues'        => ['nullable', 'boolean'],
            'consented_data'        => ['nullable', 'boolean'],
            // Legacy
            'address'               => ['nullable', 'string'],
            'bio'                   => ['nullable', 'string'],
            'profile_photo'         => ['nullable', 'string', 'url'],
            'linkedin_url'          => ['nullable', 'string', 'url'],
            'twitter_url'           => ['nullable', 'string', 'url'],
            'membership_status'     => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $user = User::with(['profile', 'roles.permissions'])->findOrFail($id);

        // Route first_name, last_name, phone to the users table
        $userFields = array_filter(
            array_intersect_key($validated, array_flip(['first_name', 'last_name', 'phone'])),
            fn ($v) => ! is_null($v),
        );

        if (! empty($userFields)) {
            $user->update($userFields);
        }

        // Everything else goes to alumni_profiles
        $profileFields = array_diff_key($validated, array_flip(['first_name', 'last_name', 'phone']));
        AlumniProfile::updateOrCreate(['user_id' => $id], $profileFields);

        $user->refresh()->load(['profile', 'roles.permissions']);

        return $this->success($this->userShape($user), 'Alumni profile updated.');
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['profile', 'roles.permissions']);

        return $this->success($this->userShape($user), 'Your profile loaded.');
    }
}
