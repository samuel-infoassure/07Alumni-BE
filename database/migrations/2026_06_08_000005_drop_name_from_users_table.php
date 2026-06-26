<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: split existing users.name into alumni_profiles.first_name / last_name
        $users = DB::table('users')->get(['id', 'name']);

        foreach ($users as $user) {
            $nameParts = preg_split('/\s+/', trim((string) $user->name ?? ''), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            $profile = DB::table('alumni_profiles')->where('user_id', $user->id)->first();

            if (! $profile) {
                DB::table('alumni_profiles')->insert([
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'membership_status' => 'active',
                    'accepted_constitution' => false,
                    'committed_dues' => false,
                    'consented_data' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif (empty($profile->first_name) && empty($profile->last_name)) {
                DB::table('alumni_profiles')->where('user_id', $user->id)->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        // Restore name from profile
        $profiles = DB::table('alumni_profiles')->get(['user_id', 'first_name', 'last_name']);

        foreach ($profiles as $profile) {
            DB::table('users')->where('id', $profile->user_id)->update([
                'name' => trim("{$profile->first_name} {$profile->last_name}"),
            ]);
        }
    }
};
