<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('nickname');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->boolean('consent_accepted')->default(false);
            $table->boolean('mpesa_card_confirmed')->default(false);
            $table->string('session_token')->unique();

            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('incorrect_answers')->default(0);
            $table->unsignedInteger('missed_answers')->default(0);
            $table->unsignedInteger('double_point_correct_answers')->default(0);
            $table->unsignedBigInteger('total_correct_response_ms')->default(0);
            $table->unsignedInteger('previous_rank')->nullable();
            $table->unsignedInteger('current_rank')->nullable();

            $table->timestamp('disqualified_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
