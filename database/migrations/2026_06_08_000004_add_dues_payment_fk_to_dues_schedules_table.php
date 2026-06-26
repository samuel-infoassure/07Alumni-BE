<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues_schedules', function (Blueprint $table) {
            $table->foreign('dues_payment_id')
                ->references('id')
                ->on('dues_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dues_schedules', function (Blueprint $table) {
            $table->dropForeign(['dues_payment_id']);
        });
    }
};
