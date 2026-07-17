<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prediction_prompt_id')->constrained()->cascadeOnDelete();

            $table->json('answer_payload')->nullable();
            $table->integer('points')->default(0);
            $table->boolean('is_correct')->default(false);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'prediction_prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_answers');
    }
};
