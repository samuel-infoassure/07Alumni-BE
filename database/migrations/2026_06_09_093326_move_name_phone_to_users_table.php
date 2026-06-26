<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('email');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('phone', 30)->nullable()->after('last_name');
        });

        // Backfill from alumni_profiles
        $profiles = DB::table('alumni_profiles')->get(['user_id', 'first_name', 'last_name', 'phone']);

        foreach ($profiles as $profile) {
            DB::table('users')->where('id', $profile->user_id)->update([
                'first_name' => $profile->first_name,
                'last_name'  => $profile->last_name,
                'phone'      => $profile->phone,
            ]);
        }

        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('user_id');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('phone', 30)->nullable()->after('last_name');
        });

        $users = DB::table('users')->get(['id', 'first_name', 'last_name', 'phone']);

        foreach ($users as $user) {
            DB::table('alumni_profiles')->where('user_id', $user->id)->update([
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'phone'      => $user->phone,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });
    }
};
