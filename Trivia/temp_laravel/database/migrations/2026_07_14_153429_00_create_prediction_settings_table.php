<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            $table->string('team_a_name');
            $table->string('team_b_name');
            $table->string('team_a_flag')->nullable();
            $table->string('team_b_flag')->nullable();

            $table->timestamp('kickoff_at')->nullable();
            $table->timestamp('prediction_lock_at')->nullable();
            $table->boolean('allow_prediction_edits')->default(true);
            $table->string('winner_rule')->default('points');

            $table->unsignedInteger('official_team_a_score')->nullable();
            $table->unsignedInteger('official_team_b_score')->nullable();
            $table->string('official_outcome')->nullable();
            $table->foreignId('official_first_goalscorer_id')->nullable()->constrained('prediction_players')->nullOnDelete();
            $table->foreignId('official_player_of_match_id')->nullable()->constrained('prediction_players')->nullOnDelete();
            $table->unsignedInteger('official_first_goal_minute')->nullable();
            $table->json('official_results_payload')->nullable();
            $table->timestamp('official_results_entered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_settings');
    }
};
