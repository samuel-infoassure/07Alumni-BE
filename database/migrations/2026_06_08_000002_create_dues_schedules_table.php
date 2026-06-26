<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('due_year');
            $table->unsignedTinyInteger('due_month'); // 1-12
            $table->decimal('amount', 10, 2)->default(1000.00);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->unsignedBigInteger('dues_payment_id')->nullable(); // FK added after dues_payments is created
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'due_year', 'due_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues_schedules');
    }
};
