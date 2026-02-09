<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('subtitle_text')->after('question_text');
            $table->dropColumn(['video_correct', 'video_wrong', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('subtitle_text');
            $table->string('video_correct')->nullable();
            $table->string('video_wrong')->nullable();
            $table->integer('order');
        });
    }
};
