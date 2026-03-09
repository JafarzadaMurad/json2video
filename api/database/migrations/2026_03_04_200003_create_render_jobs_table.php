<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('render_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 20)->default('queued');  // queued, processing, done, failed, cancelled, expired
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->string('resolution', 20)->default('full-hd');
            $table->string('quality', 20)->default('high');
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->string('output_path', 500)->nullable();
            $table->string('output_url', 500)->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('worker_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('payload_hash');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('render_jobs');
    }
};
