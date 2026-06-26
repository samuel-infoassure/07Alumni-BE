<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->text('manifesto')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedInteger('vote_count')->default(0);
            $table->timestamps();

            $table->unique(['election_id', 'user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_candidates');
    }
};
