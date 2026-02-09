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
            $table->text('question_text');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->enum('correct_answer', ['A', 'B', 'C']);
            $table->string('video_question')->nullable();
            $table->string('video_correct')->nullable();
            $table->string('video_wrong')->nullable();
            $table->integer('order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
