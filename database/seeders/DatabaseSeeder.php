<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AlumniProfile;
use App\Models\ChatGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin/test user
        $admin = User::firstOrCreate(
            ['email' => 'saudexgpt@gmail.com'],
            [
                'email'      => 'saudexgpt@gmail.com',
                'password'   => Hash::make('Password@123.'),
                'api_token'  => hash('sha256', 'admin-secret-token-2024'),
                'first_name' => 'Admin',
                'last_name'  => 'User',
            ]
        );

        AlumniProfile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'department'            => 'Electrical/Electronics Engineering',
                'membership_status'     => 'active',
                'accepted_constitution' => true,
                'committed_dues'        => true,
                'consented_data'        => true,
            ]
        );

        // Account categories
        $categories = [
            ['name' => 'Dues & Levies',         'type' => 'income',  'color' => '#16A34A', 'icon' => 'wallet'],
            ['name' => 'Donations Received',     'type' => 'income',  'color' => '#0674F9', 'icon' => 'heart'],
            ['name' => 'Event Income',           'type' => 'income',  'color' => '#C8A400', 'icon' => 'calendar'],
            ['name' => 'Grants & Sponsorship',   'type' => 'income',  'color' => '#7C3AED', 'icon' => 'gift'],
            ['name' => 'Miscellaneous Income',   'type' => 'income',  'color' => '#64748B', 'icon' => 'add-circle'],
            ['name' => 'Event Expenses',         'type' => 'expense', 'color' => '#DC2626', 'icon' => 'cash'],
            ['name' => 'Meeting Expenses',       'type' => 'expense', 'color' => '#EA580C', 'icon' => 'people'],
            ['name' => 'Administrative',         'type' => 'expense', 'color' => '#D97706', 'icon' => 'briefcase'],
            ['name' => 'Welfare & Support',      'type' => 'expense', 'color' => '#DB2777', 'icon' => 'medkit'],
            ['name' => 'Miscellaneous Expense',  'type' => 'expense', 'color' => '#64748B', 'icon' => 'ellipsis-horizontal'],
        ];

        foreach ($categories as $cat) {
            AccountCategory::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Default General chat group
        $group = ChatGroup::firstOrCreate(
            ['name' => 'General - 07 Engineering Alumni'],
            [
                'created_by' => $admin->id,
                'name' => 'General - 07 Engineering Alumni',
                'description' => 'Main group for all UNIUYO Class of 07 Engineering Alumni members.',
                'type' => 'public',
            ]
        );

        foreach ([$admin->id] as $userId) {
            if (! $group->members()->where('user_id', $userId)->exists()) {
                $group->members()->attach($userId, [
                    'role' => $userId === $admin->id ? 'admin' : 'member',
                    'joined_at' => now(),
                ]);
            }
        }

        // ── Roles & Permissions ────────────────────────────────────────
        $allPerms = [
            ['name' => 'meetings.view',    'display_name' => 'View Meetings',         'group' => 'meetings'],
            ['name' => 'meetings.create',  'display_name' => 'Create Meetings',       'group' => 'meetings'],
            ['name' => 'meetings.update',  'display_name' => 'Update Meetings',       'group' => 'meetings'],
            ['name' => 'meetings.delete',  'display_name' => 'Delete Meetings',       'group' => 'meetings'],
            ['name' => 'meetings.attend',  'display_name' => 'Record Attendance',     'group' => 'meetings'],
            ['name' => 'meetings.minutes', 'display_name' => 'Record Minutes',        'group' => 'meetings'],
            ['name' => 'events.view',      'display_name' => 'View Events',           'group' => 'events'],
            ['name' => 'events.create',    'display_name' => 'Create Events',         'group' => 'events'],
            ['name' => 'events.update',    'display_name' => 'Update Events',         'group' => 'events'],
            ['name' => 'events.delete',    'display_name' => 'Delete Events',         'group' => 'events'],
            ['name' => 'events.register',  'display_name' => 'Register for Events',   'group' => 'events'],
            ['name' => 'donations.view',   'display_name' => 'View Donations',        'group' => 'donations'],
            ['name' => 'donations.create', 'display_name' => 'Record Donations',      'group' => 'donations'],
            ['name' => 'campaigns.manage', 'display_name' => 'Manage Campaigns',      'group' => 'donations'],
            ['name' => 'accounting.view',  'display_name' => 'View Accounts',         'group' => 'accounting'],
            ['name' => 'accounting.create','display_name' => 'Record Transactions',   'group' => 'accounting'],
            ['name' => 'accounting.update','display_name' => 'Update Transactions',   'group' => 'accounting'],
            ['name' => 'accounting.delete','display_name' => 'Delete Transactions',   'group' => 'accounting'],
            ['name' => 'excos.view',       'display_name' => 'View EXCO',             'group' => 'excos'],
            ['name' => 'excos.manage',     'display_name' => 'Manage EXCO',           'group' => 'excos'],
            ['name' => 'elections.view',   'display_name' => 'View Elections',        'group' => 'elections'],
            ['name' => 'elections.create', 'display_name' => 'Create Elections',      'group' => 'elections'],
            ['name' => 'elections.manage', 'display_name' => 'Manage Elections',      'group' => 'elections'],
            ['name' => 'elections.vote',   'display_name' => 'Cast Vote',             'group' => 'elections'],
            ['name' => 'members.view',     'display_name' => 'View Members',          'group' => 'members'],
            ['name' => 'members.manage',   'display_name' => 'Manage Members',        'group' => 'members'],
            ['name' => 'chats.view',       'display_name' => 'View Chats',            'group' => 'chats'],
            ['name' => 'chats.create',     'display_name' => 'Create Chat Groups',    'group' => 'chats'],
            ['name' => 'chats.manage',     'display_name' => 'Manage Chats',          'group' => 'chats'],
            ['name' => 'reports.view',     'display_name' => 'View Reports',          'group' => 'reports'],
            ['name' => 'roles.view',       'display_name' => 'View Roles',            'group' => 'roles'],
            ['name' => 'roles.manage',     'display_name' => 'Manage Roles',          'group' => 'roles'],
        ];

        foreach ($allPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        $permMap = Permission::all()->pluck('id', 'name');

        $roleDefinitions = [
            'super_admin' => ['display_name' => 'Super Admin', 'description' => 'Full system access', 'perms' => array_keys(collect($allPerms)->pluck('name', 'name')->toArray())],
            'exco'        => ['display_name' => 'EXCO Member', 'description' => 'Executive committee member', 'perms' => [
                'meetings.view','meetings.create','meetings.update','meetings.delete','meetings.attend','meetings.minutes',
                'events.view','events.create','events.update','events.delete','events.register',
                'donations.view','donations.create','campaigns.manage',
                'excos.view','excos.manage',
                'elections.view','elections.create','elections.manage','elections.vote',
                'members.view','members.manage',
                'chats.view','chats.create','chats.manage',
                'reports.view',
            ]],
            'treasurer'   => ['display_name' => 'Treasurer', 'description' => 'Financial management', 'perms' => [
                'accounting.view','accounting.create','accounting.update','accounting.delete',
                'donations.view','donations.create',
                'reports.view','members.view',
            ]],
            'member'      => ['display_name' => 'Member', 'description' => 'Regular alumni member', 'perms' => [
                'meetings.view','meetings.attend',
                'events.view','events.register',
                'donations.view','donations.create',
                'excos.view',
                'elections.view','elections.vote',
                'members.view',
                'chats.view','chats.create',
            ]],
        ];

        foreach ($roleDefinitions as $roleName => $def) {
            $role = Role::firstOrCreate(['name' => $roleName], [
                'display_name' => $def['display_name'],
                'description'  => $def['description'],
            ]);

            $permIds = collect($def['perms'])->map(fn ($p) => $permMap[$p] ?? null)->filter()->toArray();
            $role->permissions()->sync($permIds);
        }

        // Assign roles to seed users
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $memberRole     = Role::where('name', 'member')->first();

        if ($superAdminRole && ! $admin->roles()->where('role_id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }
        
        $this->command->info('Seed complete.');
    }
}
