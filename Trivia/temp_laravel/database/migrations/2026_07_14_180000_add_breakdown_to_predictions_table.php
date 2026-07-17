<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('exact_score_correct')->default(false)->after('points');
            $table->boolean('correct_outcome')->default(false)->after('exact_score_correct');
            $table->boolean('correct_first_goalscorer')->default(false)->after('correct_outcome');
            $table->boolean('correct_player_of_match')->default(false)->after('correct_first_goalscorer');
            $table->integer('first_goal_minute_diff')->nullable()->after('correct_player_of_match');
            $table->integer('goal_difference')->nullable()->after('first_goal_minute_diff');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'exact_score_correct',
                'correct_outcome',
                'correct_first_goalscorer',
                'correct_player_of_match',
                'first_goal_minute_diff',
                'goal_difference',
            ]);
        });
    }
};
