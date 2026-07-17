<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('prompt_key');
            $table->string('prompt_label');
            $table->string('prompt_type');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('order_number')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_prompts');
    }
};
