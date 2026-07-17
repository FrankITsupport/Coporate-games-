<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreign('current_question_id')->references('id')->on('questions')->nullOnDelete();
            $table->foreign('current_prediction_prompt_id')->references('id')->on('prediction_prompts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['current_question_id']);
            $table->dropForeign(['current_prediction_prompt_id']);
        });
    }
};
