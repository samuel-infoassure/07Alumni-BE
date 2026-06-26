<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('paystack_reference')->unique();
            $table->unsignedInteger('amount_kobo'); // amount in kobo (NGN × 100)
            $table->unsignedTinyInteger('months_count');
            $table->json('schedule_ids'); // IDs of dues_schedules covered by this payment
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('paystack_data')->nullable(); // raw Paystack verify response
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues_payments');
    }
};
