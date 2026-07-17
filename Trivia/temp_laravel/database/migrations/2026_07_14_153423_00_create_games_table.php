<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('event_type')->default('both');
            $table->string('status')->default('draft');

            // FKs added later in add_current_pointers_to_games_table, once the
            // questions and prediction_prompts tables exist.
            $table->unsignedBigInteger('current_question_id')->nullable();
            $table->string('prediction_status')->nullable();
            $table->unsignedBigInteger('current_prediction_prompt_id')->nullable();

            $table->timestamp('registration_opened_at')->nullable();
            $table->timestamp('predictions_opened_at')->nullable();
            $table->timestamp('predictions_locked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
