<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->string('review_status')->default('auto_scored');

            $table->unsignedInteger('response_ms')->nullable();
            $table->unsignedInteger('base_points')->default(0);
            $table->unsignedInteger('speed_bonus')->default(0);
            $table->unsignedInteger('streak_bonus')->default(0);
            $table->unsignedInteger('total_points')->default(0);

            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_answers');
    }
};
