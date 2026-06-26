<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumni_profiles', function (Blueprint $table) {
            // Personal Information
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('nick_name')->nullable()->after('last_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('nick_name');
            $table->string('birthday', 7)->nullable()->after('gender'); // stored as MM/YYYY

            // Contact Details
            $table->string('city')->nullable()->after('phone');
            $table->string('state')->nullable()->after('city');
            $table->decimal('latitude', 10, 8)->nullable()->after('state');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('next_of_kin')->nullable()->after('longitude');
            $table->enum('kin_relationship', ['Spouse', 'Parent', 'Sibling', 'Child', 'Others'])->nullable()->after('next_of_kin');
            $table->string('kin_phone')->nullable()->after('kin_relationship');

            // Declaration
            $table->boolean('accepted_constitution')->default(false)->after('twitter_url');
            $table->boolean('committed_dues')->default(false)->after('accepted_constitution');
            $table->boolean('consented_data')->default(false)->after('committed_dues');
        });
    }

    public function down(): void
    {
        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'nick_name', 'gender', 'birthday',
                'city', 'state', 'latitude', 'longitude',
                'next_of_kin', 'kin_relationship', 'kin_phone',
                'accepted_constitution', 'committed_dues', 'consented_data',
            ]);
        });
    }
};
