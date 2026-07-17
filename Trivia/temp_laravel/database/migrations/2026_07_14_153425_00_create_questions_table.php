<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('round_name')->nullable();
            $table->text('question_text');
            $table->string('question_type')->default('multiple_choice');
            $table->unsignedInteger('duration_seconds')->default(20);
            $table->unsignedInteger('order_number')->default(0);
            $table->boolean('is_double_points')->default(false);
            $table->boolean('is_sudden_death')->default(false);
            $table->json('accepted_answers')->nullable();
            $table->boolean('requires_manual_review')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
