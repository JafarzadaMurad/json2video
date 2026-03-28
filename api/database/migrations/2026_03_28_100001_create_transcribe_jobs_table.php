<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transcribe_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 20)->default('queued'); // queued, processing, done, failed
            $table->string('src_url', 1000);                // Source audio/video URL
            $table->string('src_type', 10)->default('audio'); // audio or video
            $table->string('language', 10)->nullable();       // Detected language
            $table->decimal('language_confidence', 4, 2)->nullable();
            $table->unsignedInteger('segments')->nullable();  // Number of SRT segments
            $table->string('srt_path', 500)->nullable();      // Local file path
            $table->string('srt_url', 500)->nullable();       // Public download URL
            $table->text('error_message')->nullable();
            $table->string('worker_id', 100)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcribe_jobs');
    }
};
