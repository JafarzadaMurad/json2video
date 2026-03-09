<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('payload');
            $table->json('variables')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('thumbnail_path', 500)->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('job_id');
            $table->foreign('job_id')->references('id')->on('render_jobs');
            $table->decimal('render_seconds', 10, 2)->nullable();
            $table->unsignedInteger('credits_consumed')->nullable();
            $table->timestamp('billed_at')->useCurrent();
        });

        Schema::create('webhook_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('url', 500);
            $table->string('secret', 255)->nullable();
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_configs');
        Schema::dropIfExists('usage_logs');
        Schema::dropIfExists('templates');
    }
};
