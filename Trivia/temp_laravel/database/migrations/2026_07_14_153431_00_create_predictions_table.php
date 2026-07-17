<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->string('predicted_outcome')->nullable();
            $table->unsignedInteger('predicted_team_a_score')->nullable();
            $table->unsignedInteger('predicted_team_b_score')->nullable();
            $table->foreignId('predicted_first_goalscorer_id')->nullable()->constrained('prediction_players')->nullOnDelete();
            $table->foreignId('predicted_player_of_match_id')->nullable()->constrained('prediction_players')->nullOnDelete();
            $table->unsignedInteger('predicted_first_goal_minute')->nullable();

            $table->integer('points')->default(0);
            $table->unsignedInteger('winner_rank')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
